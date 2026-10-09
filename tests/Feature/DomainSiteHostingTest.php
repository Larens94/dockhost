<?php

// DomainSiteHostingTest.php — Panel env merge, deploy, and git summary for domain members.
//
// exports: DomainSiteHostingTest
// used_by: none
// rules:   Http::fake Dokploy; secrets redacted in Inertia; readonly 403; foreign domain 404.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Feature tests for site env + deploy.
//          grok-4.7 | cursor | 2026-10-08 | s_20261008_deploy_install | Deploy rewrites composer install before build

namespace Tests\Feature;

use App\Enums\DomainMemberRole;
use App\Enums\DomainStack;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DomainSiteHostingTest extends TestCase
{
    public function test_show_redacts_sensitive_env_and_shows_git(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "APP_NAME=Shop\nDB_PASSWORD=secret-db\nFEATURE=on\n",
                'sourceType' => 'gitlab',
                'gitlabRepository' => 'acme/shop',
                'gitlabBranch' => 'main',
            ]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteHosting.available', true)
                ->where('siteHosting.git.repository', 'acme/shop')
                ->where('siteHosting.git.branch', 'main')
                ->where('siteHosting.git.source_type', 'gitlab')
                ->has('siteHosting.variables', 3)
                ->where('siteHosting.variables.0.key', 'APP_NAME')
                ->where('siteHosting.variables.0.value', 'Shop')
                ->where('siteHosting.variables.1.key', 'DB_PASSWORD')
                ->where('siteHosting.variables.1.redacted', true)
                ->missing('siteHosting.variables.1.value'));
    }

    public function test_show_reads_github_owner_and_repository(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "APP_NAME=Shop\n",
                'sourceType' => 'github',
                'owner' => 'Larens94',
                'repository' => 'vibebridge',
                'branch' => 'main',
            ]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('siteHosting.git.source_type', 'github')
                ->where('siteHosting.git.repository', 'Larens94/vibebridge')
                ->where('siteHosting.git.branch', 'main'));
    }

    public function test_merge_env_updates_key_and_keeps_secret_when_value_blank(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "APP_NAME=Old\nDB_PASSWORD=keep-me\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->post(route('domains.site-env.update', $domain), [
                'entries' => [
                    ['key' => 'APP_NAME', 'value' => 'New'],
                    ['key' => 'DB_PASSWORD', 'value' => ''],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains($request->body(), 'APP_NAME=New')
            && str_contains($request->body(), 'DB_PASSWORD=keep-me'));
    }

    public function test_deploy_triggers_application_deploy_and_audit(): void
    {
        Http::preventStrayRequests();
        $install = 'mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci';
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "DB_HOST=infra1-mariadb\nDB_PASSWORD=keep-this\nNIXPACKS_INSTALL_CMD={$install}\nNIXPACKS_START_CMD=php artisan migrate --force\n",
            ]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect()
            ->assertSessionHas('success', 'Deploy del sito avviato su Dokploy.');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-site');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'domain.deploy',
        ]);
    }

    public function test_deploy_refreshes_stale_laravel_install_command_and_keeps_db_host(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "DB_HOST=infra1-mariadb\nDB_PASSWORD=keep-this\nNIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs && npm ci\nNIXPACKS_START_CMD=php artisan migrate --force\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect()
            ->assertSessionHas('success', 'Deploy del sito avviato su Dokploy.');

        $saved = '';
        Http::assertSent(function (Request $request) use (&$saved): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $saved = (string) $request['env'];

            return $request['applicationId'] === 'app-site';
        });

        $this->assertStringContainsString(
            'NIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci',
            $saved,
        );
        $this->assertStringContainsString('DB_HOST=infra1-mariadb', $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
        $this->assertStringContainsString('NIXPACKS_START_CMD=php artisan migrate --force', $saved);
        $this->assertSame(1, substr_count($saved, 'NIXPACKS_INSTALL_CMD='));
        $this->assertStringNotContainsString('composer install --ignore-platform-reqs &&', $saved);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-site');
    }

    public function test_deploy_writes_laravel_install_command_when_missing(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-site',
                'env' => "DB_HOST=infra1-mariadb\nDB_PASSWORD=keep-this\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect();

        $saved = '';
        Http::assertSent(function (Request $request) use (&$saved): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $saved = (string) $request['env'];

            return true;
        });

        $this->assertStringContainsString(
            'NIXPACKS_INSTALL_CMD=mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci',
            $saved,
        );
        $this->assertStringContainsString('DB_HOST=infra1-mariadb', $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
    }

    public function test_deploy_refreshes_php_composer_install_without_unsetting_db_host(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-php',
                'env' => "DB_HOST=infra1-mariadb\nDB_PASSWORD=keep-this\nNIXPACKS_INSTALL_CMD=composer install --no-dev --optimize-autoloader\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-php', null, DomainStack::Php);

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect();

        $saved = '';
        Http::assertSent(function (Request $request) use (&$saved): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $saved = (string) $request['env'];

            return true;
        });

        $this->assertStringContainsString(
            'NIXPACKS_INSTALL_CMD=composer install --no-dev --optimize-autoloader --no-interaction --no-scripts',
            $saved,
        );
        $this->assertStringContainsString('DB_HOST=infra1-mariadb', $saved);
        $this->assertStringContainsString('DB_PASSWORD=keep-this', $saved);
    }

    public function test_deploy_of_node_stack_does_not_rewrite_install_command(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-node', null, DomainStack::Node);

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect()
            ->assertSessionHas('success', 'Deploy del sito avviato su Dokploy.');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'application.one')
            || $request->url() === 'https://dokploy.test/api/application.saveEnvironment');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-node');
    }

    public function test_readonly_member_cannot_update_site_env_or_deploy(): void
    {
        Http::fake();

        $member = User::factory()->member()->create();
        $domain = $this->domainWithApplication('app-site');
        $member->domains()->attach($domain, ['role' => DomainMemberRole::Readonly->value]);

        $this->actingAs($member)
            ->post(route('domains.site-env.update', $domain), [
                'entries' => [['key' => 'FOO', 'value' => 'bar']],
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('domains.deploy', $domain))
            ->assertForbidden();
    }

    public function test_member_of_other_domain_gets_404_on_site_env_post(): void
    {
        Http::fake();

        $member = User::factory()->member()->create();
        $infrastructure = $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $allowed = $this->domainWithApplication('app-a', $infrastructure);
        $other = $this->domainWithApplication('app-b', $infrastructure);
        $member->domains()->attach($allowed, ['role' => DomainMemberRole::Developer->value]);

        $this->actingAs($member)
            ->post(route('domains.site-env.update', $other), [
                'entries' => [['key' => 'FOO', 'value' => 'bar']],
            ])
            ->assertNotFound();
    }

    private function domainWithApplication(string $applicationId, ?Infrastructure $infrastructure = null, DomainStack $stack = DomainStack::Laravel): Domain
    {
        $infrastructure ??= $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'stack' => $stack,
        ]);
        $domain->dokployApplication()->create([
            'dokploy_application_id' => $applicationId,
            'dokploy_environment_id' => 'env-1',
        ]);

        return $domain;
    }
}
