<?php

// InfrastructureStackUpdateTest.php — Feature coverage for updateStack compose push.
//
// exports: InfrastructureStackUpdateTest | InfrastructureStackUpdateTest::test_show_page_lists_template_services(): void | InfrastructureStackUpdateTest::test_update_stack_calls_compose_update_and_deploy_without_rotating_env(): void | InfrastructureStackUpdateTest::test_update_stack_attaches_phpmyadmin_domain_when_missing(): void | InfrastructureStackUpdateTest::test_update_stack_enables_redis_minio_and_pgadmin_with_domains(): void | InfrastructureStackUpdateTest::test_guests_cannot_update_infrastructure(): void | InfrastructureStackUpdateTest::test_show_keeps_tab_query_string(): void | InfrastructureStackUpdateTest::test_update_stack_accepts_boolean_dokploy_success_and_keeps_tab(): void
// used_by: none (PHPUnit entry)
// rules:   updateStack MUST push dokploy-network MariaDB aliases and MUST NOT call network.create / emit {slug}-db.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Assert shared network on stack update
// message:

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InfrastructureStackUpdateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_page_lists_template_services(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['slug' => 'infra1']);

        $this->actingAs($user)
            ->get(route('infrastructures.show', $infrastructure))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Infrastructures/Show')
                ->where('infrastructure.slug', 'infra1')
                ->has('services', 7)
                ->where('services.0.key', 'mariadb')
                ->where('services.0.hostname', 'infra1-mariadb')
                ->where('phpmyadmin_suggested_host', 'pma-infra1.cloud.silicoreautomation.com')
                ->where('pgadmin_suggested_host', 'pga-infra1.cloud.silicoreautomation.com')
                ->where('minio_suggested_host', 'minio-infra1.cloud.silicoreautomation.com')
                ->missing('panel_volumes')
                ->missing('self_application_id_configured')
                ->where('credentials.mysql.username', 'infra')
                ->where('credentials.mysql.password', 'secretmysqlapp')
                ->where('credentials.mysql_root.password', 'secretmysqlroot')
                ->where('credentials.sftp.password', 'secretsftpuser')
                ->where('infrastructure.mysql_admin_password', 'secretmysqlapp')
                ->has('infrastructure.domains', 0)
                ->has('infrastructure.database_accounts', 0));
    }

    public function test_update_stack_calls_compose_update_and_deploy_without_rotating_env(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'sftp_host_port' => 2222,
        ]);

        $this->actingAs($user)->put(route('infrastructures.update', $infrastructure), [
            'enabled_services' => ['mariadb', 'postgres', 'sftp'],
        ])->assertRedirect(route('infrastructures.show', $infrastructure));

        $infrastructure->refresh();

        $this->assertNotContains('phpmyadmin', $infrastructure->enabled_services);
        $this->assertContains('mariadb', $infrastructure->enabled_services);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/compose.update') {
                return false;
            }

            $env = (string) $request['env'];
            preg_match('/^POSTGRES_PASSWORD=(.+)$/m', $env, $postgres);
            preg_match('/^SFTP_BOOTSTRAP_PASSWORD=(.+)$/m', $env, $sftp);

            $compose = (string) $request['composeFile'];

            return $request['composeId'] === 'compose-1'
                && $request['isolatedDeployment'] === false
                && $request['createEnvFile'] === true
                && filled($postgres[1] ?? null)
                && ($sftp[1] ?? '') === 'secretsftpuser'
                && ! str_contains($compose, "\n  phpmyadmin:\n")
                && str_contains($compose, 'POSTGRES_PASSWORD: '.$postgres[1])
                && str_contains($compose, 'infra1-mariadb')
                && str_contains($compose, '"2222:22"')
                && str_contains($compose, "dokploy-network:\n        aliases:\n          - infra1-mariadb")
                && ! str_contains($compose, 'name: infra1-db')
                && ! str_contains($compose, '${INFRA_SLUG}')
                && ! str_contains($compose, '${POSTGRES_PASSWORD}');
        });

        $this->assertFalse($infrastructure->isolated_networks);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'network.create')
            || str_contains($request->url(), 'network.all'));
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.deploy'
            && $request['composeId'] === 'compose-1');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'project.create')
            || str_contains($request->url(), 'environment.create')
            || str_contains($request->url(), 'compose.create'));
    }

    public function test_update_stack_attaches_phpmyadmin_domain_when_missing(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-fix']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'phpmyadmin_domain' => null,
        ]);

        $this->actingAs($user)->put(route('infrastructures.update', $infrastructure), [
            'enabled_services' => ['mariadb', 'postgres', 'phpmyadmin', 'sftp'],
        ])->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->assertSame('pma-infra1.cloud.silicoreautomation.com', $infrastructure->refresh()->phpmyadmin_domain);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pma-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'phpmyadmin'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1');
    }

    public function test_update_stack_enables_redis_minio_and_pgadmin_with_domains(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'opt-1']),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'phpmyadmin_domain' => 'pma-infra1.cloud.silicoreautomation.com',
            'pgadmin_domain' => null,
            'minio_domain' => null,
        ]);

        $this->actingAs($user)->put(route('infrastructures.update', $infrastructure), [
            'enabled_services' => ['mariadb', 'postgres', 'sftp', 'phpmyadmin', 'pgadmin', 'redis', 'minio'],
        ])->assertRedirect(route('infrastructures.show', $infrastructure));

        $infrastructure->refresh();

        $this->assertContains('redis', $infrastructure->enabled_services);
        $this->assertContains('minio', $infrastructure->enabled_services);
        $this->assertContains('pgadmin', $infrastructure->enabled_services);
        $this->assertSame('pga-infra1.cloud.silicoreautomation.com', $infrastructure->pgadmin_domain);
        $this->assertSame('minio-infra1.cloud.silicoreautomation.com', $infrastructure->minio_domain);
        $this->assertSame('infra1-redis', $infrastructure->redis_host);
        $this->assertSame('infra1-minio', $infrastructure->minio_host);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/compose.update') {
                return false;
            }

            $compose = (string) $request['composeFile'];

            return str_contains($compose, "\n  redis:\n")
                && str_contains($compose, "\n  minio:\n")
                && str_contains($compose, "\n  pgadmin:\n")
                && str_contains($compose, 'hostname: infra1-redis')
                && str_contains($compose, 'hostname: infra1-minio');
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pga-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'pgadmin'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'minio-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'minio'
            && $request['path'] === '/'
            && $request['port'] === 9001
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1');
    }

    public function test_guests_cannot_update_infrastructure(): void
    {
        $infrastructure = Infrastructure::factory()->create();

        $this->put(route('infrastructures.update', $infrastructure), [
            'enabled_services' => ['mariadb'],
        ])->assertRedirect(route('login'));
    }

    public function test_show_keeps_tab_query_string(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create(['slug' => 'infra1']);

        $this->actingAs($user)
            ->get(route('infrastructures.show', ['infrastructure' => $infrastructure, 'tab' => 'servizi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Infrastructures/Show'));
    }

    public function test_update_stack_accepts_boolean_dokploy_success_and_keeps_tab(): void
    {
        Http::preventStrayRequests();
        $ok = Http::response('true', 200, ['Content-Type' => 'application/json']);
        Http::fake([
            'https://dokploy.test/api/compose.update' => $ok,
            'https://dokploy.test/api/compose.saveEnvironment' => $ok,
            'https://dokploy.test/api/compose.deploy' => $ok,
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'phpmyadmin_domain' => 'pma-infra1.cloud.silicoreautomation.com',
        ]);

        $this->actingAs($user)
            ->put(route('infrastructures.update', ['infrastructure' => $infrastructure, 'tab' => 'servizi']), [
                'enabled_services' => ['mariadb', 'postgres', 'sftp', 'phpmyadmin'],
            ])
            ->assertRedirect(route('infrastructures.show', ['infrastructure' => $infrastructure, 'tab' => 'servizi']));

        $this->assertSame('deployed', $infrastructure->refresh()->status);
    }
}
