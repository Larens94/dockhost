<?php

// LaravelToolkitGitDiscoveryTest.php — GitLab-backed Toolkit command catalog on status.
//
// exports: LaravelToolkitGitDiscoveryTest
// used_by: none
// rules:   Http::fake GitLab API only; never embed real tokens.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | composer.json, package.json, Console command fixtures.
// message:

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelToolkitGitDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('services.gitlab.url', 'https://gitlab.test');
        Config::set('services.gitlab.token', 'test-gitlab-token');
    }

    public function test_status_includes_git_discovered_artisan_composer_and_npm(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployFakes(),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/composer.json/raw*' => Http::response(
                json_encode([
                    'scripts' => [
                        'test' => 'vendor/bin/phpunit',
                        'danger' => 'rm -rf /',
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/package.json/raw*' => Http::response(
                json_encode([
                    'scripts' => [
                        'build' => 'vite build',
                        'dev' => 'vite',
                        'lint' => 'eslint resources/js',
                    ],
                ], JSON_THROW_ON_ERROR),
            ),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/routes%2Fconsole.php/raw*' => Http::response('<?php'),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/tree*' => Http::response([
                ['type' => 'blob', 'path' => 'app/Console/Commands/SyncRuntsCommand.php'],
            ]),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/app%2FConsole%2FCommands%2FSyncRuntsCommand.php/raw*' => Http::response(
                <<<'PHP'
                <?php
                class SyncRuntsCommand extends Command {
                    protected $signature = 'runts:sync';
                }
                PHP
            ),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->getJson(route('domains.laravel.status', $domain))
            ->assertOk()
            ->assertJsonPath('command_catalog_source', 'git')
            ->assertJsonFragment(['command' => 'runts:sync'])
            ->assertJsonFragment(['command' => 'test'])
            ->assertJsonFragment(['command' => 'run lint'])
            ->assertJsonMissing(['command' => 'danger']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'gitlab.test/api/v4/projects/'));
    }

    public function test_status_falls_back_without_gitlab_token(): void
    {
        Config::set('services.gitlab.token', null);

        Http::preventStrayRequests();
        Http::fake($this->dokployFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->getJson(route('domains.laravel.status', $domain))
            ->assertOk()
            ->assertJsonPath('command_catalog_source', 'fallback')
            ->assertJsonPath(
                'command_catalog_message',
                'Toolkit GitLab: collega GitLab su Dokploy (General → Git) oppure usa «Usa GitLab di Dokploy» / un token di gruppo opzionale sotto.',
            );

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'gitlab.test'));
    }

    public function test_status_discovers_via_dokploy_gitlab_oauth_without_panel_pat(): void
    {
        Config::set('services.gitlab.token', null);

        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployFakesWithGitlabIntegration(),
            'https://dokploy.test/api/gitlab.one*' => Http::response([
                'gitlabId' => 'gl-int-1',
                'gitlabUrl' => 'https://gitlab.test',
                'accessToken' => 'oauth-access-from-dokploy',
                'refreshToken' => 'redacted',
                'secret' => 'redacted',
            ]),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/composer.json/raw*' => Http::response(
                json_encode(['scripts' => ['test' => 'vendor/bin/phpunit']], JSON_THROW_ON_ERROR),
            ),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/package.json/raw*' => Http::response('{}'),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/files/routes%2Fconsole.php/raw*' => Http::response('<?php'),
            'https://gitlab.test/api/v4/projects/silicore%2Fvibesbridge/repository/tree*' => Http::response([]),
        ]);

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->getJson(route('domains.laravel.status', $domain))
            ->assertOk()
            ->assertJsonPath('command_catalog_source', 'git')
            ->assertJsonPath('panel_gitlab.credential_source', 'dokploy')
            ->assertJsonPath('panel_gitlab.dokploy_gitlab_available', true);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'gitlab.one'));
        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), 'gitlab.test/api/v4/projects/')
                && $request->header('Authorization') === ['Bearer oauth-access-from-dokploy'];
        });
    }

    /**
     * @return array<string, PromiseInterface>
     */
    /**
     * @return array<string, PromiseInterface>
     */
    private function dokployFakesWithGitlabIntegration(): array
    {
        return [
            ...$this->dokployFakes(),
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'appName' => 'test-vibesbridge-com',
                'applicationStatus' => 'done',
                'buildType' => 'nixpacks',
                'sourceType' => 'gitlab',
                'gitlabId' => 'gl-int-1',
                'gitlabRepository' => 'vibesbridge',
                'gitlabOwner' => 'silicore',
                'gitlabPathNamespace' => 'silicore/vibesbridge',
                'gitlabBranch' => 'main',
                'hasGitProviderAccess' => true,
            ]),
        ];
    }

    /**
     * @return array<string, PromiseInterface>
     */
    private function dokployFakes(): array
    {
        return [
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'appName' => 'test-vibesbridge-com',
                'applicationStatus' => 'done',
                'buildType' => 'nixpacks',
                'sourceType' => 'gitlab',
                'gitlabRepository' => 'silicore/vibesbridge',
                'gitlabBranch' => 'main',
            ]),
            'https://dokploy.test/api/deployment.all*' => Http::response([
                [
                    'deploymentId' => 'dep-app',
                    'status' => 'done',
                    'title' => 'Manual deployment',
                ],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppLabel*' => Http::response([
                [
                    'Name' => '/test-vibesbridge-com',
                    'Id' => 'appcontainerid',
                    'State' => 'running',
                ],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([]),
        ];
    }

    private function laravelDomain(): Domain
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
}
