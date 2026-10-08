<?php

// DomainSiteHostingTest.php — Panel env merge, deploy, and git summary for domain members.
//
// exports: DomainSiteHostingTest
// used_by: none
// rules:   Http::fake Dokploy; secrets redacted in Inertia; readonly 403; foreign domain 404.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Feature tests for site env + deploy.

namespace Tests\Feature;

use App\Enums\DomainMemberRole;
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
                ->has('siteHosting.variables', 3)
                ->where('siteHosting.variables.0.key', 'APP_NAME')
                ->where('siteHosting.variables.0.value', 'Shop')
                ->where('siteHosting.variables.1.key', 'DB_PASSWORD')
                ->where('siteHosting.variables.1.redacted', true)
                ->missing('siteHosting.variables.1.value'));
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
        Http::fake([
            'https://dokploy.test/api/application.deploy' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = $this->domainWithApplication('app-site');

        $this->actingAs($user)
            ->post(route('domains.deploy', $domain))
            ->assertRedirect()
            ->assertSessionHas('success', 'Deploy del sito avviato su Dokploy.');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.deploy'
            && $request['applicationId'] === 'app-site');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'domain.deploy',
        ]);
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

    private function domainWithApplication(string $applicationId, ?Infrastructure $infrastructure = null): Domain
    {
        $infrastructure ??= $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $domain->dokployApplication()->create([
            'dokploy_application_id' => $applicationId,
            'dokploy_environment_id' => 'env-1',
        ]);

        return $domain;
    }
}
