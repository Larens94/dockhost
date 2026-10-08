<?php

// AuditLogTest.php — Audit log recording and admin-only listing.
//
// exports: AuditLogTest
// used_by: none
// rules:   Members must not access /panel/audit.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | Audit feature tests.

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_php_settings_save_creates_audit_entry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'app-audit',
                'env' => "NIXPACKS_START_CMD=php -S 0.0.0.0:80\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $admin = User::factory()->create();
        $infrastructure = $this->panelInfrastructure([
            'dokploy_project_id' => 'proj-1',
            'dokploy_environment_id' => 'env-1',
        ]);
        $domain = Domain::factory()->laravel()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $domain->dokployApplication()->create([
            'dokploy_application_id' => 'app-audit',
            'dokploy_environment_id' => 'env-1',
        ]);

        $this->actingAs($admin)
            ->post(route('domains.php-settings.update', $domain), [
                'memory_limit' => '256M',
                'upload_max_filesize' => '64M',
                'post_max_size' => '64M',
                'max_execution_time' => 120,
                'max_input_time' => 120,
                'artisan_memory_limit' => '512M',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'domain.php_settings.save',
            'subject_type' => Domain::class,
            'subject_id' => $domain->id,
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment');
    }

    public function test_member_cannot_view_audit_page(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get('/panel/audit')
            ->assertNotFound();
    }

    public function test_admin_can_view_audit_page(): void
    {
        AuditLog::query()->create([
            'user_id' => User::factory()->create()->id,
            'action' => 'panel.smtp.save',
            'meta' => ['sync_status' => 'synced'],
        ]);

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/panel/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Audit/Index')
                ->has('logs', 1));
    }
}
