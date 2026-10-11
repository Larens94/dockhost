<?php

// DomainRuntimeEnvTest.php — APP_ENV / APP_DEBUG / LOG_LEVEL panel updates.
//
// exports: DomainRuntimeEnvTest
// used_by: none (PHPUnit entry)
// rules:   Fake Dokploy saveEnvironment + deploy; never assert secret values beyond runtime keys.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_runtime_env | Runtime env feature tests
// message:

namespace Tests\Feature;

use App\Enums\DomainStack;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainRuntimeEnvTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_runtime_env_updates_app_env_debug_and_log_level(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-runtime',
                'env' => "APP_ENV=production\nAPP_DEBUG=false\nLOG_LEVEL=info\nDB_PASSWORD=keep\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-runtime');

        $this->actingAs($user)
            ->post(route('domains.runtime-env.update', $domain), [
                'app_env' => 'local',
                'app_debug' => true,
                'log_level' => 'debug',
            ])
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains((string) $request['env'], 'APP_ENV=local')
            && str_contains((string) $request['env'], 'APP_DEBUG=true')
            && str_contains((string) $request['env'], 'LOG_LEVEL=debug')
            && str_contains((string) $request['env'], 'DB_PASSWORD=keep'));
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy');
    }

    public function test_runtime_env_save_and_deploy_triggers_deploy(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-runtime',
                'env' => "APP_ENV=production\nNIXPACKS_INSTALL_CMD=composer install\nNIXPACKS_START_CMD=php artisan migrate --force\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-runtime');

        $this->actingAs($user)
            ->post(route('domains.runtime-env.update', $domain), [
                'app_env' => 'staging',
                'app_debug' => true,
                'log_level' => 'debug',
                'deploy' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-runtime');
    }

    public function test_align_boot_env_preserves_local_app_env_and_adds_missing_log_level(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-runtime',
                'env' => "APP_ENV=local\nAPP_DEBUG=true\nAPP_KEY=base64:already\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-runtime');

        $this->actingAs($user)
            ->post(route('domains.laravel.align-env', $domain))
            ->assertRedirect();

        $env = '';
        Http::assertSent(function (Request $request) use (&$env): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) $request['env'];

            return true;
        });

        $this->assertStringContainsString('APP_ENV=local', $env);
        $this->assertStringContainsString('APP_DEBUG=true', $env);
        $this->assertStringContainsString('LOG_LEVEL=info', $env);
        $this->assertDoesNotMatchRegularExpression('/APP_ENV=production/', $env);
    }

    private function laravelDomainWithApp(string $applicationId): Domain
    {
        $domain = Domain::factory()->laravel()->create([
            'fqdn' => 'reteinazione.example.test',
            'stack' => DomainStack::Laravel,
        ]);

        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => $applicationId,
        ]);

        return $domain->fresh(['dokployApplication']);
    }
}
