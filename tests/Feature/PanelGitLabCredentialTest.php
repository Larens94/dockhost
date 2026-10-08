<?php

// PanelGitLabCredentialTest.php — Panel GitLab credential UI endpoint and Dokploy sync.
//
// exports: PanelGitLabCredentialTest
// used_by: none
// rules:   Http::fake GitLab and Dokploy; never embed real tokens.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | POST panel/gitlab/credentials encrypts and syncs.
// message:

namespace Tests\Feature;

use App\Models\PanelSetting;
use App\Models\User;
use App\Services\Panel\PanelGitLabCredentialStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelGitLabCredentialTest extends TestCase
{
    public function test_guest_cannot_store_gitlab_credentials(): void
    {
        $this->postJson('/panel/gitlab/credentials', [
            'gitlab_url' => 'https://gitlab.test',
            'token' => 'secret-token-12345',
        ])->assertUnauthorized();
    }

    public function test_store_validates_token_with_gitlab_and_syncs_dokploy(): void
    {
        Config::set('services.gitlab.token', null);
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://gitlab.test/api/v4/user' => Http::response(['id' => 1, 'username' => 'bot']),
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => "APP_KEY=base64:existing\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/panel/gitlab/credentials', [
                'gitlab_url' => 'https://gitlab.test/',
                'token' => 'read-repo-token-xyz',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sync.status', 'synced');

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request->data()['env'] ?? '');

            return str_contains($env, 'GITLAB_URL=https://gitlab.test')
                && str_contains($env, 'GITLAB_TOKEN=read-repo-token-xyz');
        });

        $encrypted = PanelSetting::value(PanelGitLabCredentialStore::KEY_TOKEN_ENCRYPTED);
        $this->assertIsString($encrypted);
        $this->assertSame('read-repo-token-xyz', Crypt::decryptString($encrypted));
        $this->assertSame('https://gitlab.test', PanelSetting::value(PanelGitLabCredentialStore::KEY_URL));
    }

    public function test_store_rejects_invalid_gitlab_token(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://gitlab.test/api/v4/user' => Http::response(['message' => '401 Unauthorized'], 401),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/panel/gitlab/credentials', [
                'gitlab_url' => 'https://gitlab.test',
                'token' => 'bad-token-12345678',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'application.saveEnvironment'));
    }
}
