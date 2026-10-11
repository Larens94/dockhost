<?php

// DomainDatabaseImportTest.php — Feature coverage for SQL dump import into a domain database.
//
// exports: DomainDatabaseImportTest
// used_by: none (PHPUnit entry)
// rules:   Mock DomainBackupManager — no live MariaDB in CI. Never assert dump SQL contents.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_db_import | Import dump HTTP tests
// message:

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\User;
use App\Services\Hosting\DomainBackupManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class DomainDatabaseImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_import_dump_calls_manager_and_redirects(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        $backup = DomainBackup::factory()->completed()->make([
            'id' => 91,
            'domain_id' => $domain->id,
            'meta' => ['source' => 'import', 'original_name' => 'site.sql'],
        ]);

        $file = UploadedFile::fake()->createWithContent('site.sql', "CREATE TABLE t (id INT);\n");

        $manager = Mockery::mock(DomainBackupManager::class);
        $manager->shouldReceive('importSqlDump')
            ->once()
            ->with(
                Mockery::on(fn (Domain $d): bool => (int) $d->id === (int) $domain->id),
                Mockery::type(UploadedFile::class),
                false,
            )
            ->andReturn($backup);
        $this->app->instance(DomainBackupManager::class, $manager);

        $this->actingAs($user)
            ->post(route('domains.database.import', $domain), [
                'dump' => $file,
                'confirm_overwrite' => '1',
            ])
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'database']))
            ->assertSessionHas('success');
    }

    public function test_import_dump_requires_confirm_overwrite(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        $file = UploadedFile::fake()->createWithContent('site.sql', "CREATE TABLE t (id INT);\n");

        $this->actingAs($user)
            ->from(route('domains.show', $domain))
            ->post(route('domains.database.import', $domain), [
                'dump' => $file,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('confirm_overwrite');
    }

    public function test_import_dump_rejects_non_sql_extension(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create();
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'not sql');

        $this->actingAs($user)
            ->from(route('domains.show', $domain))
            ->post(route('domains.database.import', $domain), [
                'dump' => $file,
                'confirm_overwrite' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('dump');
    }
}
