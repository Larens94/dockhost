<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_assignment_activates_the_subscription(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['billing_status' => 'none', 'stripe_customer_id' => null]);
        $plan = Plan::factory()->create();

        $this->actingAs($user)
            ->post(route('billing.assign'), [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
            ])
            ->assertRedirect(route('billing.index'));

        $this->assertDatabaseHas('subscriptions', [
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $this->assertSame('active', $client->fresh()->billing_status);
    }

    public function test_checkout_webhook_activates_an_incomplete_subscription(): void
    {
        $client = Client::factory()->create([
            'stripe_customer_id' => 'cus_live_1',
            'billing_status' => 'pending',
        ]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'status' => 'incomplete',
            'stripe_subscription_id' => null,
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'customer' => 'cus_live_1',
                    'subscription' => 'sub_live_1',
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->signature($payload)],
            $payload,
        )->assertOk();

        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertSame('sub_live_1', $subscription->fresh()->stripe_subscription_id);
        $this->assertSame('active', $client->fresh()->billing_status);
    }

    public function test_payment_failure_marks_the_client_past_due(): void
    {
        $client = Client::factory()->create([
            'stripe_customer_id' => 'cus_live_2',
            'billing_status' => 'active',
        ]);
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);

        $payload = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'customer' => 'cus_live_2',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->signature($payload)],
            $payload,
        )->assertOk();

        $this->assertSame('past_due', $client->fresh()->billing_status);
        $this->assertSame('past_due', $subscription->fresh()->status);
    }

    public function test_payment_failure_suspends_existing_sites_until_billing_recovers(): void
    {
        $client = Client::factory()->create([
            'stripe_customer_id' => 'cus_live_3',
            'billing_status' => 'active',
            'status' => 'active',
        ]);
        $plan = Plan::factory()->create();
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $site = Site::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
            'usage_held' => true,
            'dokploy_app_id' => 'local_bill',
        ]);

        $failed = json_encode([
            'type' => 'invoice.payment_failed',
            'data' => [
                'object' => [
                    'customer' => 'cus_live_3',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->signature($failed)],
            $failed,
        )->assertOk();

        $this->assertSame('suspended', $site->fresh()->status);
        $this->assertTrue($site->fresh()->usage_held);

        $paid = json_encode([
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'customer' => 'cus_live_3',
                    'subscription' => 'sub_live_3',
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $this->signature($paid)],
            $paid,
        )->assertOk();

        $this->assertSame('active', $client->fresh()->billing_status);
        $this->assertSame('active', $site->fresh()->status);
        $this->assertTrue($site->fresh()->usage_held);
    }

    public function test_webhook_rejects_a_bad_signature(): void
    {
        config(['dockhost.stripe.webhook_secret' => 'whsec_test']);

        $this->call(
            'POST',
            route('stripe.webhook'),
            [],
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef'],
            '{"type":"checkout.session.completed"}',
        )->assertStatus(400);
    }

    private function signature(string $payload): string
    {
        $secret = 'whsec_test';
        config(['dockhost.stripe.webhook_secret' => $secret]);
        $timestamp = time();
        $digest = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$digest}";
    }
}
