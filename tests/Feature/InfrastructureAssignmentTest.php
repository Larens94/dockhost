<?php


// InfrastructureAssignmentTest.php — InfrastructureAssignmentTest module.
//
// exports: InfrastructureAssignmentTest | InfrastructureAssignmentTest::test_domain_create_lists_only_panel_managed_infrastructures(): void | InfrastructureAssignmentTest::test_domain_cannot_use_unmanaged_or_missing_infrastructure(): void | InfrastructureAssignmentTest::test_create_database_is_rejected_when_mariadb_is_missing_or_not_deployed(): void | InfrastructureAssignmentTest::test_later_database_can_target_a_second_infrastructure(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Enums\DatabaseEngine;
use App\Models\Domain;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class InfrastructureAssignmentTest extends TestCase
{
    public function test_domain_create_lists_only_panel_managed_infrastructures(): void
    {
        $this->panelInfrastructure(['slug' => 'infra1']);
        $this->panelInfrastructure(['slug' => 'infra2']);
        $this->panelInfrastructure([
            'slug' => 'infra-old',
            'dokploy_compose_id' => null,
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)
            ->get(route('subscriptions.domains.create', $subscription))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Create')
                ->has('infrastructures', 2)
                ->where('infrastructures.0.slug', 'infra1')
                ->where('infrastructures.0.can_mysql', true)
                ->where('infrastructures.0.sftp_public_host', 'cloud.silicoreautomation.com')
                ->where('infrastructures.1.slug', 'infra2')
                ->missing('default_environment_id'));
    }

    public function test_domain_cannot_use_unmanaged_or_missing_infrastructure(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'ghost.acme.test',
            'infra_slug' => 'infra1',
        ])->assertSessionHasErrors('infra_slug');

        $this->panelInfrastructure([
            'slug' => 'infra-old',
            'dokploy_compose_id' => null,
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'old.acme.test',
            'infra_slug' => 'infra-old',
        ])->assertSessionHasErrors('infra_slug');

        $this->assertDatabaseMissing('domains', ['fqdn' => 'ghost.acme.test']);
        $this->assertDatabaseMissing('domains', ['fqdn' => 'old.acme.test']);
    }

    public function test_create_database_is_rejected_when_mariadb_is_missing_or_not_deployed(): void
    {
        $this->panelInfrastructure([
            'slug' => 'infra2',
            'status' => 'pending',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'pending.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra2',
        ])->assertSessionHasErrors('create_database');

        $this->panelInfrastructure([
            'slug' => 'infra3',
            'enabled_services' => ['postgres', 'sftp', 'sftp-users-init'],
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'nodb.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra3',
        ])->assertSessionHasErrors('create_database');
    }

    public function test_later_database_can_target_a_second_infrastructure(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $first = $this->panelInfrastructure(['slug' => 'infra1']);
        $this->panelInfrastructure(['slug' => 'infra2']);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'later.acme.test',
            'infra_slug' => 'infra1',
        ])->assertRedirect();

        $domain = Domain::query()->where('fqdn', 'later.acme.test')->firstOrFail();

        $this->assertSame($first->id, $domain->infrastructure_id);

        $this->actingAs($user)->post(route('domains.database.store', $domain), [
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra2',
        ])->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));

        $this->assertDatabaseHas('database_accounts', [
            'domain_id' => $domain->id,
            'infra_slug' => 'infra2',
            'host' => 'infra2-mariadb',
        ]);
    }
}
