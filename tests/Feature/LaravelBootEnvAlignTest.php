<?php

// LaravelBootEnvAlignTest.php — LaravelBootEnvAlignTest module.
//
// exports: LaravelBootEnvAlignTest | LaravelBootEnvAlignTest::test_align_generates_missing_app_key_and_keeps_database_password(): void | LaravelBootEnvAlignTest::test_align_does_not_overwrite_existing_app_key(): void | LaravelBootEnvAlignTest::test_align_requires_an_attached_application(): void | LaravelBootEnvAlignTest::test_guests_cannot_align_boot_env(): void
// used_by: none
// rules:   Align rewrites SESSION_DRIVER to database and SESSION_DOMAIN to the site fqdn; APP_URL/ASSET_URL to https fqdn. APP_KEY and DB secrets stay.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_session_cookie | Expect database session and host-only cookie
//          composer-2.5-fast | cursor | 2026-09-23 | s_20260923_app_url | Assert APP_URL https rewrite on align
//          grok-4.7 | cursor | 2026-10-08 | s_20261008_no_scripts | Expect composer --no-scripts on the Laravel install command
// message:

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelBootEnvAlignTest extends TestCase
{
    public function test_align_generates_missing_app_key_and_keeps_database_password(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes(
            "APP_ENV=local\nAPP_DEBUG=true\nDB_PASSWORD=keep-this\nSTRIPE_SECRET=sk_live_keep\n",
        ));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();

        $this->actingAs($user)
            ->from(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']))
            ->post(route('domains.laravel.align-env', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']))
            ->assertSessionHas('success', 'APP_KEY generata. Env di avvio allineato su Dokploy (chiavi assenti soltanto).');

        $saved = $this->savedEnvironment();

        $this->assertSame(1, preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $saved));
        $this->assertStringContainsString('APP_ENV=local', $saved);
        $this->assertStringContainsString('APP_DEBUG=true', $saved);
        $this->assertStringContainsString('APP_NAME='.$domain->fqdn, $saved);
        $this->assertStringContainsString('SESSION_DRIVER=database', $saved);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $saved);
        $this->assertStringContainsString('SESSION_DOMAIN='.$domain->fqdn, $saved);
        $this->assertStringContainsString('APP_URL=https://'.$domain->fqdn, $saved);
        $this->assertStringContainsString('ASSET_URL=https://'.$domain->fqdn, $saved);
        $this->assertStringContainsString('TRUSTED_PROXIES=*', $saved);
        $this->assertStringNotContainsString('APP_URL=http://', $saved);
        $this->assertStringContainsString('CACHE_STORE=file', $saved);
        $this->assertStringContainsString('LOG_CHANNEL=stderr', $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
        $this->assertStringContainsString('STRIPE_SECRET=sk_live_keep', $saved);
        $this->assertDoesNotMatchRegularExpression('/APP_ENV=production/', $saved);
        $this->assertSame(1, preg_match('/^APP_KEY=(.+)$/m', $saved, $matches));
        $this->assertStringNotContainsString($matches[1], (string) session('success'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && $request['applicationId'] === 'app-laravel'
            && $request['buildArgs'] === ''
            && $request['buildSecrets'] === ''
            && $request['createEnvFile'] === false);
    }

    public function test_align_does_not_overwrite_existing_app_key(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes(
            "APP_NAME=Vibes\nAPP_ENV=production\nAPP_KEY=base64:already-set-key\nAPP_DEBUG=false\nSESSION_DRIVER=file\nCACHE_STORE=file\nLOG_CHANNEL=stderr\nDB_PASSWORD=keep-this\nB2_KEY=keep-b2\n",
        ));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();

        $this->actingAs($user)
            ->post(route('domains.laravel.align-env', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']))
            ->assertSessionHas('success', 'Env di avvio allineato su Dokploy (chiavi assenti soltanto).');

        $saved = $this->savedEnvironment();

        $this->assertSame(1, substr_count($saved, 'APP_KEY='));
        $this->assertStringContainsString('APP_KEY=base64:already-set-key', $saved);
        $this->assertStringContainsString('SESSION_DRIVER=database', $saved);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $saved);
        $this->assertStringContainsString('SESSION_DOMAIN=test.vibesbridge.com', $saved);
        $this->assertStringContainsString('SESSION_COOKIE=test_vibesbridge_com_session', $saved);
        $this->assertStringContainsString('APP_URL=https://test.vibesbridge.com', $saved);
        $this->assertStringContainsString('ASSET_URL=https://test.vibesbridge.com', $saved);
        $this->assertStringContainsString('TRUSTED_PROXIES=*', $saved);
        $this->assertStringNotContainsString('SESSION_DRIVER=file', $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
        $this->assertStringContainsString('B2_KEY=keep-b2', $saved);
    }

    public function test_align_rewrites_http_app_url_to_https(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes(
            "APP_KEY=base64:already-set-key\nAPP_URL=http://test.vibesbridge.com\nASSET_URL=http://test.vibesbridge.com\nTRUSTED_PROXIES=\n",
        ));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();

        $this->actingAs($user)
            ->post(route('domains.laravel.align-env', $domain))
            ->assertSessionHas('success');

        $saved = $this->savedEnvironment();

        $this->assertStringContainsString('APP_URL=https://test.vibesbridge.com', $saved);
        $this->assertStringContainsString('ASSET_URL=https://test.vibesbridge.com', $saved);
        $this->assertStringContainsString('TRUSTED_PROXIES=*', $saved);
        $this->assertStringNotContainsString('APP_URL=http://', $saved);
        $this->assertStringNotContainsString('ASSET_URL=http://', $saved);
    }

    public function test_align_requires_an_attached_application(): void
    {
        $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->create([
            'fqdn' => 'test.vibesbridge.com',
            'infra_slug' => 'infra1',
        ]);

        $this->actingAs(User::factory()->create())
            ->from(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']))
            ->post(route('domains.laravel.align-env', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'laravel']))
            ->assertSessionHasErrors('align_env');
    }

    public function test_laravel_deploy_config_writes_nixpacks_env_without_exec(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes("APP_KEY=base64:already\nDB_PASSWORD=keep-this\n"));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();

        $this->actingAs($user)
            ->post(route('domains.laravel.deploy-config', $domain))
            ->assertRedirect(route('domains.show', [
                'domain' => $domain,
                'tab' => 'laravel',
                'section' => 'deploy',
            ]))
            ->assertSessionHas('success');

        $saved = $this->savedEnvironment();

        $this->assertStringContainsString("APP_KEY=base64:already\n", $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
        $this->assertStringContainsString('NIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci', $saved);
        $this->assertStringContainsString('mkdir -p /var/log/nginx /var/cache/nginx storage/framework/sessions', $saved);
        $this->assertStringContainsString('chmod -R a+rwx storage bootstrap/cache', $saved);
        $this->assertStringContainsString('php artisan migrate --force', $saved);
        $this->assertStringContainsString('nginx -c /nginx.conf', $saved);
        $this->assertStringNotContainsString('artisan serve', $saved);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'docker.executeCommand')
            || str_contains($request->url(), 'application.executeCommand'));
    }

    public function test_laravel_deploy_config_replaces_stale_nixpacks_keys_and_keeps_secrets(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes("DB_PASSWORD=keep-this\nNIXPACKS_INSTALL_CMD=yarn install\nNIXPACKS_START_CMD=php artisan serve\n"));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();

        $this->actingAs($user)
            ->post(route('domains.laravel.deploy-config', $domain))
            ->assertSessionHas('success', 'Build e avvio scritti su Dokploy: NIXPACKS_INSTALL_CMD, NIXPACKS_START_CMD, NIXPACKS_BUILD_CMD. Lancia Deploy del sito perché Nixpacks li legga.');

        $saved = $this->savedEnvironment();

        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
        $this->assertStringContainsString('NIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci', $saved);
        $this->assertStringNotContainsString('yarn install', $saved);
        $this->assertStringNotContainsString('php artisan serve', $saved);
        $this->assertStringContainsString('storage/framework/sessions', $saved);
        $this->assertSame(1, substr_count($saved, 'NIXPACKS_INSTALL_CMD='));
        $this->assertSame(1, substr_count($saved, 'NIXPACKS_START_CMD='));
    }

    public function test_laravel_deploy_config_rejects_non_laravel_stack(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->alignFakes(''));

        $user = User::factory()->create();
        $domain = $this->alignedDomain();
        $domain->update(['stack' => 'php']);

        $this->actingAs($user)
            ->from(route('domains.show', $domain))
            ->post(route('domains.laravel.deploy-config', $domain))
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHasErrors('laravel_deploy');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment');
    }

    public function test_guests_cannot_align_boot_env(): void
    {
        $domain = $this->alignedDomain();

        $this->post(route('domains.laravel.align-env', $domain))->assertRedirect(route('login'));
        $this->post(route('domains.laravel.deploy-config', $domain))->assertRedirect(route('login'));
    }

    /**
     * @return array<string, PromiseInterface>
     */
    private function alignFakes(string $env): array
    {
        return [
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'env' => $env,
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ];
    }

    private function alignedDomain(): Domain
    {
        $infrastructure = $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->laravel()->create([
            'fqdn' => 'test.vibesbridge.com',
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-laravel',
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
