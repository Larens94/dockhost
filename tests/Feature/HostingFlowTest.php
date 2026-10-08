<?php


// HostingFlowTest.php — HostingFlowTest module.
//
// exports: HostingFlowTest | HostingFlowTest::test_happy_path_creates_customer_space_and_domain_without_database(): void | HostingFlowTest::test_domain_can_optionally_create_a_shared_database(): void | HostingFlowTest::test_domain_can_optionally_attach_laravel_when_environment_is_configured(): void | HostingFlowTest::test_laravel_attach_fails_without_infrastructure_environment_id(): void | HostingFlowTest::test_database_and_laravel_can_be_added_after_domain_creation(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Enums\DatabaseEngine;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\ServicePlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class HostingFlowTest extends TestCase
{
    public function test_happy_path_creates_customer_space_and_domain_without_database(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldNotReceive('provision');
        $this->instance(MysqlProvisioner::class, $mysql);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'Acme Hosting',
            'email' => 'ops@acme.test',
        ])->assertRedirect();

        $customer = Customer::query()->where('name', 'Acme Hosting')->firstOrFail();

        $this->actingAs($user)->post(route('service-plans.store'), [
            'name' => 'Starter',
            'slug' => 'starter',
        ])->assertRedirect();

        $plan = ServicePlan::query()->where('slug', 'starter')->firstOrFail();

        $this->actingAs($user)->post(route('subscriptions.store'), [
            'customer_id' => $customer->id,
            'service_plan_id' => $plan->id,
            'name' => 'acme-web',
        ])->assertRedirect();

        $subscription = Subscription::query()->where('name', 'acme-web')->firstOrFail();
        $this->panelInfrastructure();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'create_database' => false,
            'stack' => 'none',
            'infra_slug' => 'infra1',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'shop.acme.test')->firstOrFail()))
            ->assertSessionHas('revealed_credential', function (array $payload): bool {
                return count($payload) === 1
                    && ($payload[0]['kind'] ?? null) === 'sftp'
                    && filled($payload[0]['password'] ?? null);
            });

        $this->assertDatabaseHas('domains', [
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'fqdn' => 'shop.acme.test',
        ]);

        $this->assertDatabaseMissing('database_accounts', [
            'domain_id' => Domain::query()->where('fqdn', 'shop.acme.test')->value('id'),
        ]);

        $this->assertDatabaseHas('sftp_users', [
            'domain_id' => Domain::query()->where('fqdn', 'shop.acme.test')->value('id'),
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://infra1-sftp-sync:8787/sync');
    }

    public function test_domain_can_optionally_create_a_shared_database(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->panelInfrastructure();
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'db.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra1',
            'stack' => 'none',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'db.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('database_accounts', [
            'engine' => DatabaseEngine::Mysql->value,
            'host' => 'infra1-mariadb',
        ]);
    }

    public function test_domain_can_optionally_attach_laravel_when_environment_is_configured(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach();

        $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
        ]);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'laravel.acme.test',
            'stack' => 'laravel',
            'infra_slug' => 'infra1',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'laravel.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('dokploy_applications', [
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-infra-1',
        ]);

        $this->assertDatabaseCount('database_accounts', 0);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.create'
            && $request['environmentId'] === 'env-infra-1');
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.deploy');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/mounts.create'
            && $request['volumeName'] === 'infra1_data');
        Http::assertSentCount(7);
    }

    public function test_laravel_attach_fails_without_infrastructure_environment_id(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $this->panelInfrastructure(['dokploy_environment_id' => '']);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'missing-env.acme.test',
            'stack' => 'laravel',
            'infra_slug' => 'infra1',
        ])->assertSessionHasErrors('stack');

        $this->assertDatabaseMissing('domains', [
            'fqdn' => 'missing-env.acme.test',
        ]);
    }

    public function test_database_and_laravel_can_be_added_after_domain_creation(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach('app-2', 'dom-2');

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->panelInfrastructure();
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'later.acme.test',
            'infra_slug' => 'infra1',
            'stack' => 'none',
        ])->assertRedirect();

        $domain = Domain::query()->where('fqdn', 'later.acme.test')->firstOrFail();

        $this->actingAs($user)->post(route('domains.database.store', $domain), [
            'engine' => DatabaseEngine::Mysql->value,
        ])->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertSessionHas('revealed_credential', function (array $payload): bool {
                return ($payload['kind'] ?? null) === 'database' && filled($payload['password'] ?? null);
            });

        $infrastructure = $domain->infrastructure()->firstOrFail();

        $this->actingAs($user)->post(route('domains.laravel.store', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.create'
            && $request['environmentId'] === $infrastructure->dokploy_environment_id);
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.deploy');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/mounts.create'
            && $request['serviceId'] === 'app-2');

        $this->assertDatabaseHas('database_accounts', [
            'domain_id' => $domain->id,
            'engine' => DatabaseEngine::Mysql->value,
        ]);
        $this->assertDatabaseHas('dokploy_applications', [
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-2',
        ]);

        Http::preventStrayRequests(false);
        Http::fake();

        $this->actingAs($user)
            ->get(route('laravel-toolkit.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LaravelToolkit/Index')
                ->has('domains', 1)
                ->where('domains.0.fqdn', 'later.acme.test')
                ->where(
                    'domains.0.dokploy_application_url',
                    'https://dokploy.test/dashboard/project/'.$infrastructure->dokploy_project_id.'/environment/'.$infrastructure->dokploy_environment_id.'/services/application/app-2',
                ));
    }
}
