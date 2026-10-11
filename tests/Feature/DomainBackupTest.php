<?php

// DomainBackupTest.php — Feature coverage for domain DB backup / restore / align routes.
//
// exports: DomainBackupTest
// used_by: none (PHPUnit entry)
// rules:   Mock DomainBackupManager / EnvironmentAligner — no live MariaDB in CI.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_domain_backup | Backup + align HTTP tests
// message:

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\Infrastructure;
use App\Models\InfrastructureEnvironment;
use App\Models\User;
use App\Services\Hosting\DomainBackupManager;
use App\Services\Hosting\EnvironmentAligner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class DomainBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_database_backup_calls_manager_and_redirects(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        $backup = DomainBackup::factory()->completed()->make([
            'id' => 42,
            'domain_id' => $domain->id,
        ]);

        $manager = Mockery::mock(DomainBackupManager::class);
        $manager->shouldReceive('createDatabaseBackup')
            ->once()
            ->with(Mockery::on(fn (Domain $d): bool => (int) $d->id === (int) $domain->id))
            ->andReturn($backup);
        $this->app->instance(DomainBackupManager::class, $manager);

        $this->actingAs($user)
            ->post(route('domains.backups.database.store', $domain))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']));
    }

    public function test_restore_database_backup_rejects_foreign_backup(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        $other = Domain::factory()->create();
        $backup = DomainBackup::factory()->completed()->create([
            'domain_id' => $other->id,
        ]);

        $this->actingAs($user)
            ->post(route('domains.backups.restore', [$domain, $backup]))
            ->assertNotFound();
    }

    public function test_align_from_refuses_production_overwrite_without_confirm(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create();
        $production = InfrastructureEnvironment::factory()->production()->create([
            'infrastructure_id' => $infrastructure->id,
            'dokploy_environment_id' => $infrastructure->dokploy_environment_id,
            'dokploy_compose_id' => $infrastructure->dokploy_compose_id,
        ]);
        $source = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'infrastructure_environment_id' => $production->id,
        ]);
        $target = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'infrastructure_environment_id' => $production->id,
        ]);

        $aligner = Mockery::mock(EnvironmentAligner::class);
        $aligner->shouldReceive('alignDatabase')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'align' => 'Rifiuto overwrite di production senza conferma esplicita.',
            ]));
        $this->app->instance(EnvironmentAligner::class, $aligner);

        $this->actingAs($user)
            ->from(route('domains.show', $target))
            ->post(route('domains.align-from', $target), [
                'source_domain_id' => $source->id,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('align');
    }

    public function test_align_from_calls_aligner_with_confirm_flag(): void
    {
        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create();
        $source = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $target = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
        ]);
        $backup = DomainBackup::factory()->completed()->make([
            'id' => 7,
            'domain_id' => $source->id,
        ]);

        $aligner = Mockery::mock(EnvironmentAligner::class);
        $aligner->shouldReceive('alignDatabase')
            ->once()
            ->with(
                Mockery::on(fn (Domain $d): bool => (int) $d->id === (int) $source->id),
                Mockery::on(fn (Domain $d): bool => (int) $d->id === (int) $target->id),
                true,
            )
            ->andReturn($backup);
        $this->app->instance(EnvironmentAligner::class, $aligner);

        $this->actingAs($user)
            ->post(route('domains.align-from', $target), [
                'source_domain_id' => $source->id,
                'allow_overwrite_production' => true,
            ])
            ->assertRedirect(route('domains.show', ['domain' => $target, 'tab' => 'database']));
    }
}
