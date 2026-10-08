<?php

// SyncPanelGitlabEnvTest.php — Panel GitLab env sync to Dokploy via artisan.
//
// exports: SyncPanelGitlabEnvTest
// used_by: none
// rules:   Http::fake Dokploy only; never embed real tokens.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | saveEnvironment merges GITLAB_* keys.
// message:

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncPanelGitlabEnvTest extends TestCase
{
    public function test_command_skips_without_gitlab_token(): void
    {
        Config::set('services.gitlab.token', null);

        $this->artisan('dokhosts:sync-panel-gitlab-env')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_command_pushes_gitlab_env_to_panel_application(): void
    {
        Config::set('services.gitlab.url', 'https://gitlab.test');
        Config::set('services.gitlab.token', 'read-repo-token');
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => "APP_KEY=base64:existing\nDOKPLOY_URL=https://dokploy.test\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $this->artisan('dokhosts:sync-panel-gitlab-env')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request->data()['env'] ?? '');

            return str_contains($env, 'GITLAB_URL=https://gitlab.test')
                && str_contains($env, 'GITLAB_TOKEN=read-repo-token')
                && str_contains($env, 'APP_KEY=base64:existing');
        });
    }

    public function test_command_updates_existing_gitlab_keys(): void
    {
        Config::set('services.gitlab.url', 'https://gitlab.test');
        Config::set('services.gitlab.token', 'new-token');
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => "GITLAB_URL=https://old.test\nGITLAB_TOKEN=old-token\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $this->artisan('dokhosts:sync-panel-gitlab-env')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request->data()['env'] ?? '');

            return str_contains($env, 'GITLAB_URL=https://gitlab.test')
                && str_contains($env, 'GITLAB_TOKEN=new-token')
                && ! str_contains($env, 'old-token');
        });
    }
}
