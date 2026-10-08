<?php

// SubscriptionTest.php — SubscriptionTest module.
//
// exports: SubscriptionTest | SubscriptionTest::test_authenticated_user_can_create_a_subscription(): void | SubscriptionTest::test_authenticated_user_can_create_a_spazio_from_a_customer(): void | SubscriptionTest::test_subscription_index_renders_inertia_page(): void | SubscriptionTest::test_subscription_index_lists_dokploy_targets(): void | SubscriptionTest::test_authenticated_user_deletes_a_space_and_its_dokploy_apps(): void | SubscriptionTest::test_deleting_a_space_with_one_domain_requires_the_fqdn(): void | SubscriptionTest::test_delete_is_rejected_when_confirmation_does_not_match(): void | SubscriptionTest::test_space_without_domains_can_be_deleted_by_typing_its_name(): void | SubscriptionTest::test_guests_cannot_create_a_subscription(): void | SubscriptionTest::test_guests_cannot_delete_a_subscription(): void | SubscriptionTest::test_non_admins_cannot_delete_a_subscription(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Cover space delete confirmation and Dokploy teardown.
// message:

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\ServicePlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
                ->where('subscriptions.0.name', 'acme-web')
                ->where('subscriptions.0.deletion_confirmation', 'acme-web')
                ->has('subscriptions.0.deletion_targets', 0));
    }

    public function test_subscription_index_lists_dokploy_targets(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);
        $infrastructure = $this->panelInfrastructure();
        $domain = $this->domainOn($subscription, $infrastructure, 'shop.acme.test');
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-shop',
        ]);

        $this->actingAs($user)
            ->get(route('subscriptions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('subscriptions.0.deletion_confirmation', 'shop.acme.test')
                ->has('subscriptions.0.deletion_targets', 1)
                ->where('subscriptions.0.deletion_targets.0.fqdn', 'shop.acme.test')
                ->where('subscriptions.0.deletion_targets.0.dokploy_service', 'shop.acme.test'));
    }

    public function test_authenticated_user_deletes_a_space_and_its_dokploy_apps(): void
    {
        $this->fakeDokployDecommission();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);
        $infrastructure = $this->panelInfrastructure();
        $shop = $this->domainOn($subscription, $infrastructure, 'shop.acme.test');
        $blog = $this->domainOn($subscription, $infrastructure, 'blog.acme.test');
        DokployApplication::factory()->create([
            'domain_id' => $shop->id,
            'dokploy_application_id' => 'app-shop',
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $blog->id,
            'dokploy_application_id' => 'app-blog',
        ]);

        $this->actingAs($user)
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'acme-web',
            ])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseMissing('domains', ['id' => $shop->id]);
        $this->assertDatabaseMissing('domains', ['id' => $blog->id]);
        $this->assertDatabaseMissing('dokploy_applications', ['dokploy_application_id' => 'app-shop']);
        $this->assertDatabaseMissing('dokploy_applications', ['dokploy_application_id' => 'app-blog']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.delete'
            && $request['applicationId'] === 'app-shop');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.delete'
            && $request['applicationId'] === 'app-blog');
    }

    public function test_deleting_a_space_with_one_domain_requires_the_fqdn(): void
    {
        $this->fakeDokployDecommission();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);
        $infrastructure = $this->panelInfrastructure();
        $domain = $this->domainOn($subscription, $infrastructure, 'shop.acme.test');
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-shop',
        ]);

        $this->actingAs($user)
            ->from(route('subscriptions.show', $subscription))
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'acme-web',
            ])
            ->assertRedirect(route('subscriptions.show', $subscription))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseHas('domains', ['id' => $domain->id]);

        $this->actingAs($user)
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'shop.acme.test',
            ])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.delete'
            && $request['applicationId'] === 'app-shop');
    }

    public function test_delete_is_rejected_when_confirmation_does_not_match(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);

        $this->actingAs($user)
            ->from(route('subscriptions.index'))
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'altro-nome',
            ])
            ->assertRedirect(route('subscriptions.index'))
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
        Http::assertNothingSent();
    }

    public function test_space_without_domains_can_be_deleted_by_typing_its_name(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);

        $this->actingAs($user)
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'acme-web',
            ])
            ->assertRedirect(route('subscriptions.index'));

        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_guests_cannot_delete_a_subscription(): void
    {
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);

        $this->delete(route('subscriptions.destroy', $subscription), [
            'confirmation' => 'acme-web',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    public function test_non_admins_cannot_delete_a_subscription(): void
    {
        $user = User::factory()->member()->create();
        $subscription = Subscription::factory()->create(['name' => 'acme-web']);

        $this->actingAs($user)
            ->delete(route('subscriptions.destroy', $subscription), [
                'confirmation' => 'acme-web',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    private function domainOn(Subscription $subscription, Infrastructure $infrastructure, string $fqdn): Domain
    {
        return Domain::factory()->create([
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => $fqdn,
        ]);
    }

    private function fakeDokployDecommission(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
        ]);
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
