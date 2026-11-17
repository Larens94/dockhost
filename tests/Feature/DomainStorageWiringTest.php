<?php

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\SftpUser;
use App\Models\StorageShare;
use App\Models\User;
use App\Support\LaravelStorageBootstrapShell;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainStorageWiringTest extends TestCase
{
    public function test_wire_storage_syncs_sftp_and_updates_laravel_start_cmd(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $startWithout = 'mkdir storage && php artisan migrate --force';
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-1',
                'env' => "NIXPACKS_START_CMD={$startWithout}\nDOKHOSTS_STORAGE_PATH=/data/shop\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-sync-1', 'Id' => 'sftpsyncid', 'State' => 'running'],
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
        ]);

        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.example.test',
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $sftpUser = SftpUser::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'username' => 'u_shop',
            'home_path' => rtrim($infrastructure->storage_root, '/').'/u_shop',
        ]);
        StorageShare::factory()->create([
            'domain_id' => $domain->id,
            'path' => $sftpUser->home_path,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post(route('domains.storage.wire', $domain), ['deploy_now' => true]);

        $response->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'sftp']));
        $response->assertSessionHas('success');

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request->data()['env'] ?? '');

            return str_contains($env, LaravelStorageBootstrapShell::MARKER)
                && str_contains($env, 'NIXPACKS_START_CMD=');
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://dokploy.test/api/application.deploy';
        });
    }
}
