<?php

// DomainBackupScheduleTest.php — Automatic domain DB backup schedule + settings.
//
// exports: DomainBackupScheduleTest
// used_by: none (PHPUnit entry)
// rules:   Mock DomainBackupManager — no live MariaDB. Assert due/skip and settings route.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_auto_backup | Schedule + settings coverage
// message:

namespace Tests\Feature;

use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\User;
use App\Services\Hosting\DomainBackupManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class DomainBackupScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_update_backup_settings(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'backup_enabled' => false,
            'backup_frequency' => 'weekly',
            'backup_retain_days' => 7,
        ]);

        $this->actingAs($user)
            ->post(route('domains.backups.settings', $domain), [
                'backup_enabled' => true,
                'backup_schedule' => '0 * * * *',
                'backup_retain_days' => 30,
            ])
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));

        $domain->refresh();
        $this->assertTrue($domain->backup_enabled);
        $this->assertSame('0 * * * *', $domain->backup_schedule);
        $this->assertSame(30, $domain->backup_retain_days);
    }

    public function test_scheduled_command_runs_due_domain_and_skips_recent(): void
    {
        config(['dokploy.domain_backups_enabled' => false]);

        $due = Domain::factory()->create([
            'backup_enabled' => true,
            'backup_frequency' => 'daily',
            'backup_last_ran_at' => now()->subDays(2),
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $due->id,
            'infrastructure_id' => $due->infrastructure_id,
            'infra_slug' => $due->infra_slug,
        ]);

        $recent = Domain::factory()->create([
            'backup_enabled' => true,
            'backup_frequency' => 'daily',
            'backup_last_ran_at' => now()->subHours(2),
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $recent->id,
            'infrastructure_id' => $recent->infrastructure_id,
            'infra_slug' => $recent->infra_slug,
        ]);

        $manager = Mockery::mock(DomainBackupManager::class);
        $manager->shouldReceive('createDatabaseBackup')
            ->once()
            ->with(Mockery::on(fn (Domain $domain): bool => (int) $domain->id === (int) $due->id))
            ->andReturn(DomainBackup::factory()->completed()->make([
                'id' => 99,
                'domain_id' => $due->id,
            ]));
        $this->app->instance(DomainBackupManager::class, $manager);

        $this->artisan('domains:run-database-backups')
            ->assertSuccessful();

        $this->assertNotNull($due->fresh()->backup_last_ran_at);
        $this->assertTrue($due->fresh()->backup_last_ran_at->greaterThan(now()->subMinute()));
        $this->assertTrue($recent->fresh()->backup_last_ran_at->equalTo($recent->backup_last_ran_at));
    }
}
