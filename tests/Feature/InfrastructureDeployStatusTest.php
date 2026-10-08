<?php

// InfrastructureDeployStatusTest.php — Deploy-status polling after compose create/update.
//
// exports: InfrastructureDeployStatusTest | InfrastructureDeployStatusTest::test_verify_status_persists_deployed_when_mariadb_and_enabled_services_run(): void | InfrastructureDeployStatusTest::test_verify_status_marks_degraded_when_a_container_is_restarting(): void | InfrastructureDeployStatusTest::test_verify_status_marks_failed_when_last_deployment_failed(): void | InfrastructureDeployStatusTest::test_create_does_not_mark_deployed_until_dokploy_reports_healthy_containers(): void | InfrastructureDeployStatusTest::test_guests_cannot_verify_status(): void
// used_by: none (PHPUnit entry)
// rules:   Create path must not need network.create fakes — infra compose ships on dokploy-network only.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Drop isolated network fakes on create
// message:

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfrastructureDeployStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_verify_status_persists_deployed_when_mariadb_and_enabled_services_run(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->dokployComposeStatusFakes());

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deploying',
            'last_error' => 'stale',
        ]);

        $this->actingAs($user)
            ->getJson(route('infrastructures.deploy-status', $infrastructure))
            ->assertOk()
            ->assertJsonPath('status', 'deployed')
            ->assertJsonPath('compose_status', 'done')
            ->assertJsonPath('last_deployment.status', 'done')
            ->assertJsonPath('last_deployment.title', 'Compose deploy')
            ->assertJsonPath('containers.0.service', 'mariadb')
            ->assertJsonPath('containers.0.state', 'running');

        $this->assertSame('deployed', $infrastructure->refresh()->status);
        $this->assertNull($infrastructure->last_error);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.one?composeId=compose-1');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/deployment.allByCompose?composeId=compose-1');
        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch?',
        ));
    }

    public function test_verify_status_marks_degraded_when_a_container_is_restarting(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                ['status' => 'done', 'title' => 'Compose deploy', 'createdAt' => '2026-09-21T00:00:00.000Z'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-mariadb-1', 'State' => 'running', 'Status' => 'Up 1 minute'],
                ['Name' => '/infra1-postgres-1', 'State' => 'restarting', 'Status' => 'Restarting'],
                ['Name' => '/infra1-sftp-1', 'State' => 'running', 'Status' => 'Up 1 minute'],
                ['Name' => '/infra1-phpmyadmin-1', 'State' => 'running', 'Status' => 'Up 1 minute'],
            ]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deployed',
        ]);

        $this->actingAs($user)
            ->getJson(route('infrastructures.deploy-status', $infrastructure))
            ->assertOk()
            ->assertJsonPath('status', 'degraded');

        $this->assertSame('degraded', $infrastructure->refresh()->status);
        $this->assertSame('Alcuni servizi sono in riavvio su Dokploy.', $infrastructure->last_error);
    }

    public function test_verify_status_marks_failed_when_last_deployment_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'error',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                [
                    'status' => 'error',
                    'title' => 'Compose deploy',
                    'createdAt' => '2026-09-21T00:00:00.000Z',
                    'errorMessage' => 'compose up failed',
                ],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deploying',
        ]);

        $this->actingAs($user)
            ->getJson(route('infrastructures.deploy-status', $infrastructure))
            ->assertOk()
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('last_deployment.status', 'error')
            ->assertJsonPath('last_error', 'compose up failed');

        $this->assertSame('failed', $infrastructure->refresh()->status);
        $this->assertSame('compose up failed', $infrastructure->last_error);
    }

    public function test_create_does_not_mark_deployed_until_dokploy_reports_healthy_containers(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/project.create' => Http::response([
                'project' => ['projectId' => 'proj-1'],
                'environment' => ['environmentId' => 'env-host-1'],
            ]),
            'https://dokploy.test/api/compose.create' => Http::response(['composeId' => 'compose-1']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-1']),
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'running',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                ['status' => 'running', 'title' => 'Compose deploy', 'createdAt' => '2026-09-21T00:00:00.000Z'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra1',
        ])->assertRedirect();

        $this->assertSame('deploying', Infrastructure::query()->where('slug', 'infra1')->value('status'));
    }

    public function test_guests_cannot_verify_status(): void
    {
        $infrastructure = Infrastructure::factory()->create();

        $this->getJson(route('infrastructures.deploy-status', $infrastructure))
            ->assertUnauthorized();
    }
}
