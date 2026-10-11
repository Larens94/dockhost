<?php

// InfrastructureEnvironmentTest.php — Feature coverage for multi-env under Infrastructure.
//
// exports: InfrastructureEnvironmentTest
// used_by: none (PHPUnit entry)
// rules:   Fake Dokploy environment.create; production ensure is local-only.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_infra_env | Environment HTTP + provisioner tests
// message:

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\InfrastructureEnvironment;
use App\Models\User;
use App\Services\Infra\InfrastructureEnvironmentProvisioner;
use App\Services\Infra\MysqlCredentialHealer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfrastructureEnvironmentTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ensure_default_production_is_idempotent(): void
    {
        $infrastructure = Infrastructure::factory()->create();
        $provisioner = app(InfrastructureEnvironmentProvisioner::class);

        $first = $provisioner->ensureDefaultProduction($infrastructure);
        $second = $provisioner->ensureDefaultProduction($infrastructure);

        $this->assertTrue($first->is($second));
        $this->assertSame('production', $first->slug);
        $this->assertTrue($first->is_default);
        $this->assertSame(1, $infrastructure->environments()->count());
    }

    public function test_store_environment_creates_dokploy_env(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/environment.create' => Http::response([
                'environmentId' => 'env-staging-1',
            ]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'dokploy_project_id' => 'proj-1',
        ]);
        InfrastructureEnvironment::factory()->production()->create([
            'infrastructure_id' => $infrastructure->id,
            'dokploy_environment_id' => $infrastructure->dokploy_environment_id,
            'dokploy_compose_id' => $infrastructure->dokploy_compose_id,
        ]);

        $this->actingAs($user)
            ->post(route('infrastructures.environments.store', $infrastructure), [
                'name' => 'staging',
            ])
            ->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->assertDatabaseHas('infrastructure_environments', [
            'infrastructure_id' => $infrastructure->id,
            'slug' => 'staging',
            'dokploy_environment_id' => 'env-staging-1',
            'is_default' => false,
        ]);
    }

    public function test_heal_mysql_route_calls_healer(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create();

        $healer = \Mockery::mock(MysqlCredentialHealer::class);
        $healer->shouldReceive('heal')
            ->once()
            ->andReturn([
                'aligned_from_compose' => true,
                'infra_login_ok' => true,
                'root_login_ok' => false,
                'altered_infra_user' => false,
                'message' => 'Login admin MariaDB OK.',
            ]);
        $this->app->instance(MysqlCredentialHealer::class, $healer);

        $this->actingAs($user)
            ->post(route('infrastructures.mysql.heal', $infrastructure))
            ->assertRedirect(route('infrastructures.show', $infrastructure))
            ->assertSessionHas('success');
    }

    public function test_show_includes_environments_relation(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['slug' => 'infra1']);
        InfrastructureEnvironment::factory()->production()->create([
            'infrastructure_id' => $infrastructure->id,
        ]);

        $this->actingAs($user)
            ->get(route('infrastructures.show', ['infrastructure' => $infrastructure, 'tab' => 'ambienti']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Infrastructures/Show')
                ->has('infrastructure.environments', 1)
                ->where('infrastructure.environments.0.slug', 'production'));
    }
}
