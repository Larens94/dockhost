<?php


// SubscriptionTest.php — SubscriptionTest module.
//
// exports: SubscriptionTest | SubscriptionTest::test_authenticated_user_can_create_a_subscription(): void | SubscriptionTest::test_authenticated_user_can_create_a_spazio_from_a_customer(): void | SubscriptionTest::test_subscription_index_renders_inertia_page(): void | SubscriptionTest::test_guests_cannot_create_a_subscription(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServicePlan;
use App\Models\Subscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_authenticated_user_can_create_a_subscription(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $plan = ServicePlan::factory()->unlimited()->create();

        $response = $this->actingAs($user)->post(route('subscriptions.store'), [
            'customer_id' => $customer->id,
            'service_plan_id' => $plan->id,
            'name' => 'acme-web',
        ]);

        $subscription = Subscription::query()->where('name', 'acme-web')->first();

        $this->assertNotNull($subscription);
        $response->assertRedirect(route('subscriptions.show', $subscription));
        $this->assertSame($customer->id, $subscription->customer_id);
        $this->assertSame($plan->id, $subscription->service_plan_id);
    }

    public function test_authenticated_user_can_create_a_spazio_from_a_customer(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $plan = ServicePlan::factory()->unlimited()->create();

        $this->actingAs($user)
            ->post(route('customers.subscriptions.store', $customer), [
                'name' => 'vibesbridge.space',
                'service_plan_id' => $plan->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('subscriptions', [
            'customer_id' => $customer->id,
            'name' => 'vibesbridge.space',
        ]);
    }

    public function test_subscription_index_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create(['name' => 'acme-web']);

        $this->actingAs($user)
            ->get(route('subscriptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Subscriptions/Index')
                ->has('subscriptions', 1)
                ->where('subscriptions.0.name', 'acme-web'));
    }

    public function test_guests_cannot_create_a_subscription(): void
    {
        $customer = Customer::factory()->create();
        $plan = ServicePlan::factory()->create();

        $this->post(route('subscriptions.store'), [
            'customer_id' => $customer->id,
            'service_plan_id' => $plan->id,
            'name' => 'acme-web',
        ])->assertRedirect(route('login'));
    }
}
