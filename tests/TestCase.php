<?php

// TestCase.php — Base PHPUnit case with Dokploy HTTP helpers.
//
// exports: TestCase | TestCase::panelInfrastructure(array $overrides = []): Infrastructure | TestCase::dokployPanelApplicationFakes(array $mounts = []): array | TestCase::dokployProjectDiscoveryFakes(array $projects = []): array | TestCase::dokployComposeStatusFakes(): array | TestCase::fakeSftpSync(): void | TestCase::fakeLaravelDokployAttach(string $applicationId = 'app-1', string $domainId = 'dom-1'): void | TestCase::dokployIsolatedNetworkFakes(): array
// used_by: tests/Feature/* | tests/Unit/*
// rules:   dokployIsolatedNetworkFakes is ONLY for InfraDataNetworksTest — create/update infra tests must not need network.create.
//          Panel helpers use hostname DB hosts (${slug}-mariadb), matching shared dokploy-network model.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Clarify unused isolated network fakes
// message:

namespace Tests;

use App\Models\Infrastructure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $root = sys_get_temp_dir().'/silicore-host-tests/'.spl_object_id($this);
        config([
            'infra.storage_root' => $root,
            'infra.sftp.users_file' => $root.'/.sftp/users.conf',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function panelInfrastructure(array $overrides = []): Infrastructure
    {
        $slug = $overrides['slug'] ?? 'infra1';

        return Infrastructure::factory()->create(array_merge([
            'slug' => $slug,
            'status' => 'deployed',
            'storage_root' => (string) config('infra.storage_root'),
            'sftp_users_file' => (string) config('infra.sftp.users_file'),
            'mysql_host' => $slug.'-mariadb',
            'postgres_host' => $slug.'-postgres',
            'sftp_host' => $slug.'-sftp',
        ], $overrides));
    }

    /**
     * @param  list<array<string, mixed>>  $mounts
     * @return array<string, PromiseInterface>
     */
    protected function dokployPanelApplicationFakes(array $mounts = []): array
    {
        return [
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'mounts' => $mounts,
            ]),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $projects
     * @return array<string, PromiseInterface>
     */
    protected function dokployProjectDiscoveryFakes(array $projects = []): array
    {
        return [
            'https://dokploy.test/api/project.all' => Http::response($projects),
            'https://dokploy.test/api/project.one*' => Http::response([
                'environments' => [],
            ]),
            'https://dokploy.test/api/settings.cleanUnusedVolumes' => Http::response(['ok' => true]),
        ];
    }

    /**
     * @return array<string, PromiseInterface>
     */
    protected function dokployComposeStatusFakes(): array
    {
        return [
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                [
                    'deploymentId' => 'dep-1',
                    'status' => 'done',
                    'title' => 'Compose deploy',
                    'createdAt' => '2026-09-21T00:00:00.000Z',
                    'errorMessage' => null,
                ],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/stack-mariadb-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-postgres-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-sftp-sync-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-sftp-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-phpmyadmin-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-pgadmin-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-redis-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
                ['Name' => '/stack-minio-1', 'State' => 'running', 'Status' => 'Up 2 minutes'],
            ]),
        ];
    }

    protected function fakeSftpSync(): void
    {
        Http::fake([
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-sync-1', 'Id' => 'sftpsyncid', 'State' => 'running'],
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
        ]);
    }

    protected function fakeLaravelDokployAttach(string $applicationId = 'app-1', string $domainId = 'dom-1'): void
    {
        Http::fake([
            'https://dokploy.test/api/application.create' => Http::response(['applicationId' => $applicationId]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.saveGitProvider' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => $domainId]),
            'https://dokploy.test/api/mounts.create' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
        ]);
    }

    /**
     * HTTP fakes for the UNUSED InfraDataNetworks helper only.
     * Create/update infra paths must NOT need these (shared dokploy-network).
     *
     * @return array<string, PromiseInterface>
     */
    protected function dokployIsolatedNetworkFakes(): array
    {
        return [
            'https://dokploy.test/api/network.all' => Http::response([]),
            'https://dokploy.test/api/network.create' => Http::response(['networkId' => 'net-isolated']),
        ];
    }
}
