<?php

// DomainPhpSettingsTest.php — Panel PHP settings persist and merge Dokploy env.
//
// exports: DomainPhpSettingsTest
// used_by: none
// rules:   saveEnvironment must keep unrelated secrets; PHP keys replaced from panel values.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | Http::fake saveEnvironment coverage.
//          grok-4.7 | cursor | 2026-10-08 | s_20261008_deploy_install | Deploy-now rewrites composer install in the same save

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainPhpSettingsTest extends TestCase
{
    public function test_save_php_settings_merges_env_without_wiping_secrets(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-php',
                'env' => "APP_KEY=base64:keep\nDB_PASSWORD=secret-db\nSTRIPE_SECRET=sk_keep\nNIXPACKS_START_CMD=php artisan migrate --force && nginx -c /nginx.conf\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-php');

        $this->actingAs($user)
            ->from(route('domains.show', ['domain' => $domain, 'tab' => 'generale']))
            ->post(route('domains.php-settings.update', $domain), [
                'memory_limit' => '512M',
                'upload_max_filesize' => '128M',
                'post_max_size' => '128M',
                'max_execution_time' => 300,
                'max_input_time' => 300,
                'artisan_memory_limit' => '768M',
                'deploy_now' => false,
            ])
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHas(
                'success',
                'Env aggiornato. Dopo Salva PHP serve deploy del sito perché le direttive si applichino.',
            );

        $domain->refresh();
        $this->assertSame('512M', $domain->php_settings['memory_limit']);
        $this->assertSame(300, $domain->php_settings['max_execution_time']);

        $saved = $this->savedEnvironment();

        $this->assertStringContainsString('APP_KEY=base64:keep', $saved);
        $this->assertStringContainsString('DB_PASSWORD=secret-db', $saved);
        $this->assertStringContainsString('STRIPE_SECRET=sk_keep', $saved);
        $this->assertStringContainsString('PHP_MEMORY_LIMIT=512M', $saved);
        $this->assertStringContainsString('UPLOAD_MAX_FILESIZE=128M', $saved);
        $this->assertStringContainsString('POST_MAX_SIZE=128M', $saved);
        $this->assertStringContainsString('MAX_EXECUTION_TIME=300', $saved);
        $this->assertStringContainsString('MAX_INPUT_TIME=300', $saved);
        $this->assertStringContainsString('RUNTS_SYNC_MEMORY_LIMIT=768M', $saved);
        $this->assertStringContainsString('ARTISAN_MEMORY_LIMIT=768M', $saved);
        $this->assertStringContainsString('dokhosts.ini', $saved);
        $this->assertSame(1, substr_count($saved, '; : __DOKHOSTS_INI__; '));
        $this->assertStringNotContainsString('__mkdir', $saved);
        $this->assertStringNotContainsString('NIXPACKS_INSTALL_CMD=', $saved);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && $request['applicationId'] === 'app-php');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'application.deploy'));
    }

    public function test_deploy_now_triggers_application_deploy(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-php',
                'env' => "DB_HOST=infra1-mariadb\nDB_PASSWORD=secret-db\nNIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs && npm ci\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-php');

        $this->actingAs($user)
            ->post(route('domains.php-settings.update', $domain), [
                'memory_limit' => '256M',
                'upload_max_filesize' => '64M',
                'post_max_size' => '64M',
                'max_execution_time' => 120,
                'max_input_time' => 120,
                'artisan_memory_limit' => '512M',
                'deploy_now' => true,
            ])
            ->assertSessionHas('success', 'Env aggiornato e deploy avviato.');

        $saved = $this->savedEnvironment();
        $this->assertStringContainsString(
            'NIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci',
            $saved,
        );
        $this->assertStringContainsString('DB_HOST=infra1-mariadb', $saved);
        $this->assertStringContainsString('DB_PASSWORD=secret-db', $saved);
        $this->assertStringContainsString('PHP_MEMORY_LIMIT=256M', $saved);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-php');
    }

    public function test_readonly_member_cannot_save_php_settings(): void
    {
        Http::fake();

        $readonly = User::factory()->member()->create();
        $domain = $this->laravelDomainWithApp('app-php');
        $domain->members()->attach($readonly->id, ['role' => 'readonly']);

        $this->actingAs($readonly)
            ->post(route('domains.php-settings.update', $domain), [
                'memory_limit' => '256M',
                'upload_max_filesize' => '64M',
                'post_max_size' => '64M',
                'max_execution_time' => 120,
                'max_input_time' => 120,
                'artisan_memory_limit' => '512M',
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_align_boot_env_includes_php_settings(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'env' => "APP_KEY=base64:existing\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomainWithApp('app-laravel');
        $domain->update([
            'php_settings' => [
                'memory_limit' => '384M',
                'artisan_memory_limit' => '640M',
            ],
        ]);

        $this->actingAs($user)
            ->post(route('domains.laravel.align-env', $domain))
            ->assertRedirect();

        $saved = $this->savedEnvironment();
        $this->assertStringContainsString('PHP_MEMORY_LIMIT=384M', $saved);
        $this->assertStringContainsString('RUNTS_SYNC_MEMORY_LIMIT=640M', $saved);
    }

    private function laravelDomainWithApp(string $applicationId): Domain
    {
        $infrastructure = $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => $applicationId,
            'dokploy_environment_id' => 'env-1',
        ]);

        return $domain->fresh(['dokployApplication', 'infrastructure']);
    }

    private function savedEnvironment(): string
    {
        $env = '';

        Http::assertSent(function (Request $request) use (&$env): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) $request['env'];

            return true;
        });

        return $env;
    }
}
