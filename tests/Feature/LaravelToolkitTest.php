<?php

// LaravelToolkitTest.php — LaravelToolkitTest module.
//
// exports: LaravelToolkitTest | LaravelToolkitTest::test_status_reports_running_container_and_last_deploy_without_env(): void | LaravelToolkitTest::test_status_disables_tools_when_container_is_not_running(): void | LaravelToolkitTest::test_artisan_does_not_call_missing_docker_execute_command(): void | LaravelToolkitTest::test_artisan_rejects_tinker(): void | LaravelToolkitTest::test_composer_does_not_call_missing_docker_execute_command(): void | LaravelToolkitTest::test_exec_is_blocked_when_container_is_down(): void | LaravelToolkitTest::test_guests_cannot_use_toolkit_endpoints(): void
// used_by: none
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_toolkit_terminal | Terminal workflow: mode=terminal 200, terminal_workflow on status.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_toolkit_catalog | Assert command_catalog on status; npm exec blocked.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\User;
use App\Services\Dokploy\DokployClient;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LaravelToolkitTest extends TestCase
{
    public function test_status_reports_running_container_and_last_deploy_without_env(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->getJson(route('domains.laravel.status', $domain))
            ->assertOk()
            ->assertJsonPath('app_url', 'https://test.vibesbridge.com')
            ->assertJsonPath('infra_slug', 'infra1')
            ->assertJsonPath('application_status', 'done')
            ->assertJsonPath('last_deploy_title', 'Manual deployment')
            ->assertJsonPath('last_deploy_status', 'done')
            ->assertJsonPath('container_running', true)
            ->assertJsonPath('ready', true)
            ->assertJsonPath('exec_available', false)
            ->assertJsonPath('terminal_workflow', true)
            ->assertJsonPath(
                'dokploy_terminal_url',
                'https://dokploy.test/dashboard/project/proj-1/environment/env-1/services/application/app-laravel?tab=general',
            )
            ->assertJsonPath('exec_message', DokployClient::TERMINAL_WORKFLOW_OVERVIEW_MESSAGE)
            ->assertJsonPath('build_type', 'nixpacks')
            ->assertJsonPath('git_configured', true)
            ->assertJsonStructure([
                'command_catalog' => [
                    'artisan' => [
                        ['id', 'label', 'commands'],
                    ],
                    'composer',
                    'npm',
                ],
                'command_catalog_source',
                'command_catalog_message',
            ])
            ->assertJsonPath('command_catalog_source', 'fallback')
            ->assertJsonPath('gitlab_url_default', 'https://git.silicoreautomation.com')
            ->assertJsonPath('panel_gitlab.default_url', 'https://git.silicoreautomation.com')
            ->assertJsonMissingPath('env')
            ->assertJsonMissing(['APP_KEY', 'DB_PASSWORD']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'application.one'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'deployment.all'));
    }

    public function test_status_disables_tools_when_container_is_not_running(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes(running: false));

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->getJson(route('domains.laravel.status', $domain))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('container_running', false)
            ->assertJsonPath('exec_available', false)
            ->assertJsonFragment(['message' => 'Prima configura GitLab e Deploy su Dokploy: il container applicazione non è in esecuzione.']);
    }

    public function test_artisan_does_not_call_missing_docker_execute_command(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->postJson(route('domains.laravel.artisan', $domain), [
                'command' => 'migrate:status',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'terminal')
            ->assertJsonPath('command', 'php artisan migrate:status')
            ->assertJsonPath('message', DokployClient::TERMINAL_WORKFLOW_RESULT_MESSAGE)
            ->assertJsonMissing(['Not found']);

        $this->assertDokployExecWasNotCalled();
    }

    public function test_artisan_rejects_tinker(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->postJson(route('domains.laravel.artisan', $domain), [
                'command' => 'tinker',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('command');

        $this->assertDokployExecWasNotCalled();
    }

    public function test_composer_does_not_call_missing_docker_execute_command(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->postJson(route('domains.laravel.composer', $domain), [
                'command' => 'dump-autoload',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'terminal')
            ->assertJsonPath('command', 'composer dump-autoload');

        $this->assertDokployExecWasNotCalled();
    }

    public function test_npm_does_not_call_missing_docker_execute_command(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes());

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->postJson(route('domains.laravel.npm', $domain), [
                'command' => 'run build',
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'terminal')
            ->assertJsonPath('command', 'npm run build');

        $this->assertDokployExecWasNotCalled();
    }

    public function test_exec_is_blocked_when_container_is_down(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->toolkitFakes(running: false));

        $user = User::factory()->create();
        $domain = $this->laravelDomain();

        $this->actingAs($user)
            ->postJson(route('domains.laravel.artisan', $domain), [
                'command' => 'optimize:clear',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.command.0', 'Prima configura GitLab e Deploy su Dokploy: il container applicazione non è in esecuzione.');

        $this->assertDokployExecWasNotCalled();
    }

    public function test_guests_cannot_use_toolkit_endpoints(): void
    {
        $domain = $this->laravelDomain();

        $this->getJson(route('domains.laravel.status', $domain))->assertUnauthorized();
        $this->postJson(route('domains.laravel.artisan', $domain), ['command' => 'about'])->assertUnauthorized();
    }

    private function assertDokployExecWasNotCalled(): void
    {
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'docker.executeCommand')
            || str_contains($request->url(), 'application.executeCommand')
            || str_contains($request->url(), 'docker.exec'));
    }

    /**
     * @return array<string, PromiseInterface>
     */
    private function toolkitFakes(bool $running = true): array
    {
        return [
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-laravel',
                'appName' => 'test-vibesbridge-com',
                'applicationStatus' => 'done',
                'buildType' => 'nixpacks',
                'sourceType' => 'gitlab',
                'gitlabRepository' => 'silicore/vibesbridge',
                'env' => "APP_KEY=base64:secret\nDB_PASSWORD=never-show",
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
                    'State' => $running ? 'running' : 'exited',
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
