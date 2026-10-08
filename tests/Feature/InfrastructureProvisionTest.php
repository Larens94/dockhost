<?php

// InfrastructureProvisionTest.php — Feature coverage for panel infra create → Dokploy compose.
//
// exports: InfrastructureProvisionTest | InfrastructureProvisionTest::test_authenticated_user_creates_a_new_dokploy_project_and_compose(): void | InfrastructureProvisionTest::test_create_purges_leftover_dokploy_stacks_for_the_same_slug(): void | InfrastructureProvisionTest::test_authenticated_user_creates_second_infrastructure_as_a_separate_project(): void | InfrastructureProvisionTest::test_creates_production_environment_when_project_create_omits_it(): void | InfrastructureProvisionTest::test_infrastructure_index_renders_inertia_page(): void | InfrastructureProvisionTest::test_infrastructure_create_renders_inertia_page(): void | InfrastructureProvisionTest::test_create_with_optional_services_attaches_pgadmin_and_minio_domains(): void | InfrastructureProvisionTest::test_guests_cannot_create_infrastructure(): void | InfrastructureProvisionTest::test_dokploy_failure_after_local_row_is_persisted_as_failed(): void
// used_by: none (PHPUnit entry)
// rules:   Create path MUST assert isolated_networks=false and compose references dokploy-network for MariaDB.
//          MUST NOT expect name: {slug}-db / {slug}-storage. MUST NOT require network.create fakes on create.
//          One infra slug = one compose; DB_HOST hostnames stay ${slug}-mariadb on the shared network.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Expect shared dokploy-network on create
// message:

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InfrastructureProvisionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_creates_a_new_dokploy_project_and_compose(): void
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
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra1',
            'name' => 'Primary',
            'template' => 'base',
        ]);

        $infrastructure = Infrastructure::query()->where('slug', 'infra1')->firstOrFail();

        $response->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->assertDatabaseHas('infrastructures', [
            'slug' => 'infra1',
            'name' => 'Primary',
            'template' => 'base',
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-host-1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deployed',
            'mysql_host' => 'infra1-mariadb',
            'postgres_host' => 'infra1-postgres',
            'sftp_host' => 'infra1-sftp',
            'sftp_host_port' => 2222,
            'phpmyadmin_domain' => 'pma-infra1.cloud.silicoreautomation.com',
            'storage_root' => '/data',
            'sftp_users_file' => '/etc/sftp/users.conf',
            'isolated_networks' => false,
        ]);
        $this->assertFalse($infrastructure->isolated_networks);

        $this->assertNotEmpty($infrastructure->sftp_sync_token);

        $this->assertContains('phpmyadmin', $infrastructure->enabled_services);
        $this->assertNotContains('redis', $infrastructure->enabled_services);
        $this->assertNotContains('minio', $infrastructure->enabled_services);
        $this->assertNotContains('pgadmin', $infrastructure->enabled_services);
        $this->assertNull($infrastructure->pgadmin_domain);
        $this->assertNull($infrastructure->minio_domain);
        $this->assertNotEmpty($infrastructure->postgres_admin_password);
        $this->assertNotEmpty($infrastructure->sftp_bootstrap_password);
        $this->assertStringNotContainsString(':', (string) $infrastructure->sftp_bootstrap_password);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.create'
            && $request['name'] === 'infra1');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/environment.create');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.create'
            && $request['environmentId'] === 'env-host-1'
            && $request['name'] === 'infra1'
            && $request['appName'] === 'infra1'
            && $request['composeType'] === 'docker-compose'
            && str_contains((string) $request['composeFile'], 'hostname: infra1-mariadb')
            && str_contains((string) $request['composeFile'], "dokploy-network:\n        aliases:\n          - infra1-mariadb")
            && ! str_contains((string) $request['composeFile'], 'name: infra1-db')
            && ! str_contains((string) $request['composeFile'], 'name: infra1-storage')
            && str_contains((string) $request['composeFile'], '"2222:22"')
            && str_contains((string) $request['composeFile'], "\n  sftp-sync:\n")
            && str_contains((string) $request['composeFile'], 'infra1-sftp-sync')
            && ! str_contains((string) $request['composeFile'], '${INFRA_SLUG}')
            && ! str_contains((string) $request['composeFile'], "\n  redis:\n")
            && ! str_contains((string) $request['composeFile'], "\n  minio:\n")
            && ! str_contains((string) $request['composeFile'], "\n  pgadmin:\n"));

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'network.create')
            || str_contains($request->url(), 'network.all'));

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/compose.update') {
                return false;
            }

            $env = (string) $request['env'];
            preg_match('/^POSTGRES_PASSWORD=(.+)$/m', $env, $postgres);
            preg_match('/^SFTP_BOOTSTRAP_PASSWORD=(.+)$/m', $env, $sftp);
            $compose = (string) $request['composeFile'];

            return $request['isolatedDeployment'] === false
                && $request['createEnvFile'] === true
                && str_contains($env, "INFRA_SLUG=infra1\n")
                && str_contains($env, 'SFTP_SYNC_TOKEN=')
                && filled($postgres[1] ?? null)
                && filled($sftp[1] ?? null)
                && ! str_contains((string) $sftp[1], ':')
                && str_contains($compose, 'POSTGRES_PASSWORD: '.$postgres[1])
                && ! str_contains($compose, '${POSTGRES_PASSWORD}')
                && str_contains($compose, 'mariadb -hinfra1-mariadb')
                && ! str_contains($compose, 'mysql -h')
                && str_contains($compose, 'hostname: infra1-mariadb')
                && str_contains($compose, "dokploy-network:\n        aliases:\n          - infra1-mariadb")
                && ! str_contains($compose, 'name: infra1-db')
                && str_contains($compose, '"2222:22"');
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.saveEnvironment'
            && str_contains((string) $request['env'], 'POSTGRES_PASSWORD='));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pma-infra1.cloud.silicoreautomation.com'
            && $request['path'] === '/'
            && $request['serviceName'] === 'phpmyadmin'
            && $request['composeId'] === 'compose-1'
            && $request['domainType'] === 'compose'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && in_array($request['serviceName'] ?? null, ['pgadmin', 'minio'], true));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.deploy'
            && $request['composeId'] === 'compose-1');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/mounts.create');
    }

    public function test_create_purges_leftover_dokploy_stacks_for_the_same_slug(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/project.all' => Http::response([
                ['projectId' => 'proj-stale', 'name' => 'infra1'],
            ]),
            'https://dokploy.test/api/project.one*' => Http::response([
                'environments' => [
                    [
                        'composes' => [
                            ['composeId' => 'compose-stale'],
                        ],
                    ],
                ],
            ]),
            'https://dokploy.test/api/compose.stop' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/project.remove' => Http::response(['ok' => true]),
            'https://dokploy.test/api/settings.cleanUnusedVolumes' => Http::response(['ok' => true]),
            'https://dokploy.test/api/project.create' => Http::response([
                'project' => ['projectId' => 'proj-1'],
                'environment' => ['environmentId' => 'env-host-1'],
            ]),
            'https://dokploy.test/api/compose.create' => Http::response(['composeId' => 'compose-1']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-1']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra1',
        ])->assertRedirect();

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.delete'
            && $request['composeId'] === 'compose-stale'
            && $request['deleteVolumes'] === true);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.remove'
            && $request['projectId'] === 'proj-stale');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/settings.cleanUnusedVolumes');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.create'
            && $request['name'] === 'infra1');
    }

    public function test_authenticated_user_creates_second_infrastructure_as_a_separate_project(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/project.create' => Http::sequence()
                ->push([
                    'project' => ['projectId' => 'proj-1'],
                    'environment' => ['environmentId' => 'env-host-1'],
                ])
                ->push([
                    'project' => ['projectId' => 'proj-2'],
                    'environment' => ['environmentId' => 'env-host-2'],
                ]),
            'https://dokploy.test/api/compose.create' => Http::sequence()
                ->push(['composeId' => 'compose-1'])
                ->push(['composeId' => 'compose-2']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-2']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra1',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra2',
        ])->assertRedirect();

        $this->assertDatabaseHas('infrastructures', [
            'slug' => 'infra2',
            'name' => 'infra2',
            'dokploy_project_id' => 'proj-2',
            'dokploy_environment_id' => 'env-host-2',
            'dokploy_compose_id' => 'compose-2',
            'mysql_host' => 'infra2-mariadb',
            'postgres_host' => 'infra2-postgres',
            'sftp_host' => 'infra2-sftp',
            'sftp_host_port' => 2223,
            'phpmyadmin_domain' => 'pma-infra2.cloud.silicoreautomation.com',
        ]);

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra2',
        ])->assertSessionHasErrors('slug');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.create'
            && $request['name'] === 'infra2');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.create'
            && $request['environmentId'] === 'env-host-2'
            && $request['name'] === 'infra2'
            && $request['appName'] === 'infra2'
            && str_contains((string) $request['composeFile'], 'hostname: infra2-mariadb')
            && str_contains((string) $request['composeFile'], 'name: infra2_data')
            && str_contains((string) $request['composeFile'], '"2223:22"')
            && ! str_contains((string) $request['composeFile'], 'infra1-mariadb')
            && ! str_contains((string) $request['composeFile'], '"2222:22"')
            && ! str_contains((string) $request['composeFile'], '${INFRA_SLUG}'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.update'
            && str_contains((string) $request['env'], 'INFRA_SLUG=infra2')
            && str_contains((string) $request['env'], 'SFTP_HOST_PORT=2223')
            && str_contains((string) $request['composeFile'], 'hostname: infra2-sftp')
            && str_contains((string) $request['composeFile'], '"2223:22"'));
    }

    public function test_creates_production_environment_when_project_create_omits_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/project.create' => Http::response(['projectId' => 'proj-3']),
            'https://dokploy.test/api/environment.create' => Http::response(['environmentId' => 'env-created']),
            'https://dokploy.test/api/compose.create' => Http::response(['composeId' => 'compose-3']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-3']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infra3',
        ])->assertRedirect();

        $this->assertDatabaseHas('infrastructures', [
            'slug' => 'infra3',
            'dokploy_project_id' => 'proj-3',
            'dokploy_environment_id' => 'env-created',
            'sftp_host_port' => 2224,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/environment.create'
            && $request['name'] === 'production'
            && $request['projectId'] === 'proj-3');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.create'
            && $request['environmentId'] === 'env-created');
    }

    public function test_infrastructure_index_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        Infrastructure::factory()->create(['slug' => 'infra1']);

        $this->actingAs($user)
            ->get(route('infrastructures.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Infrastructures/Index')
                ->has('infrastructures', 1)
                ->where('infrastructures.0.slug', 'infra1')
                ->missing('infrastructures.0.mysql_admin_password')
                ->missing('infrastructures.0.mysql_root_password'));
    }

    public function test_infrastructure_create_renders_inertia_page(): void
    {
        $user = User::factory()->create();
        Infrastructure::factory()->create(['slug' => 'infra1']);

        $this->actingAs($user)
            ->get(route('infrastructures.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Infrastructures/Create')
                ->where('suggested_slug', 'infra2')
                ->has('catalog'));
    }

    public function test_create_with_optional_services_attaches_pgadmin_and_minio_domains(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/project.create' => Http::response([
                'project' => ['projectId' => 'proj-opt'],
                'environment' => ['environmentId' => 'env-opt'],
            ]),
            'https://dokploy.test/api/compose.create' => Http::response(['composeId' => 'compose-opt']),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'dom-opt']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('infrastructures.store'), [
            'slug' => 'infraopt',
            'enabled_services' => ['mariadb', 'postgres', 'sftp', 'phpmyadmin', 'pgadmin', 'redis', 'minio'],
        ])->assertRedirect();

        $infrastructure = Infrastructure::query()->where('slug', 'infraopt')->firstOrFail();

        $this->assertSame('pma-infraopt.cloud.silicoreautomation.com', $infrastructure->phpmyadmin_domain);
        $this->assertSame('pga-infraopt.cloud.silicoreautomation.com', $infrastructure->pgadmin_domain);
        $this->assertSame('minio-infraopt.cloud.silicoreautomation.com', $infrastructure->minio_domain);
        $this->assertContains('redis', $infrastructure->enabled_services);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.create'
            && str_contains((string) $request['composeFile'], "\n  redis:\n")
            && str_contains((string) $request['composeFile'], "\n  minio:\n")
            && str_contains((string) $request['composeFile'], "\n  pgadmin:\n"));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pga-infraopt.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'pgadmin'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-opt');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'minio-infraopt.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'minio'
            && $request['path'] === '/'
            && $request['port'] === 9001
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-opt');
    }

    public function test_guests_cannot_create_infrastructure(): void
    {
        $this->post(route('infrastructures.store'), [
            'slug' => 'infra1',
        ])->assertRedirect(route('login'));
    }

    public function test_dokploy_failure_after_local_row_is_persisted_as_failed(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/project.create' => Http::response([
                'message' => 'Dokploy project.create failed',
            ], 500),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)->from(route('infrastructures.index'))->post(route('infrastructures.store'), [
            'slug' => 'infrafail',
        ])->assertRedirect(route('infrastructures.index'))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('infrastructures', [
            'slug' => 'infrafail',
            'status' => 'failed',
            'last_error' => 'Dokploy project.create failed',
        ]);
    }
}
