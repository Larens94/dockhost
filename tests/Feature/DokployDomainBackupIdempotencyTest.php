<?php

// DokployDomainBackupIdempotencyTest.php — Reuses existing Dokploy compose backups instead of creating duplicates.
//
// exports: DokployDomainBackupIdempotencyTest
// used_by: none
// rules:   Http fakes only — no live Dokploy.
// agent:   composer-2.5-fast | cursor | 2026-10-21 | s_dokploy_domain_backup | Idempotent schedule ensure
// message:

namespace Tests\Feature;

use App\Enums\DatabaseEngine;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Services\Hosting\DokployDomainBackupService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokployDomainBackupIdempotencyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ensure_schedule_reuses_matching_backup_on_compose_without_create(): void
    {
        config(['dokploy.domain_backups_enabled' => true]);

        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'dokploy_backup_destination_id' => 'dest-1',
            'mysql_root_password' => 'root-secret',
        ]);
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'asterfidelity.silicoreautomation.com',
            'dokploy_backup_id' => null,
            'backup_enabled' => true,
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'database_name' => 'd_asterfidelity_silicoreautomation_com',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'backups' => [
                    [
                        'backupId' => 'bk-existing',
                        'database' => 'd_asterfidelity_silicoreautomation_com',
                        'prefix' => 'infra1/asterfidelity.silicoreautomation.com',
                        'destinationId' => 'dest-1',
                        'backupType' => 'compose',
                    ],
                ],
            ]),
            'https://dokploy.test/api/backup.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/backup.create' => Http::response(['backupId' => 'bk-new']),
        ]);

        app(DokployDomainBackupService::class)->ensureSchedule($domain->refresh());

        $this->assertSame('bk-existing', $domain->fresh()->dokploy_backup_id);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/backup.update'
            && $request['backupId'] === 'bk-existing');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/backup.create');
    }

    public function test_list_remote_files_finds_sql_under_compose_s3_root(): void
    {
        $infrastructure = $this->panelInfrastructure([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'dokploy_backup_destination_id' => 'dest-1',
            'mysql_root_password' => 'root-secret',
        ]);
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'neoai.silicoreautomation.com',
        ]);
        DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'database_name' => 'd_neoai_silicoreautomation_com',
            'engine' => DatabaseEngine::Mysql,
        ]);

        $path = 'infra1-7guive_mariadb/infra1/neoai.silicoreautomation.com/2026-10-22T10-50-00.sql.gz';

        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1-7guive',
            ]),
            'https://dokploy.test/api/backup.listBackupFiles*' => Http::response([
                ['Name' => '2026-10-22T10-50-00.sql.gz', 'Path' => $path, 'IsDir' => false, 'Size' => 11_600_000],
            ]),
        ]);

        $domain->refresh()->load(['infrastructure', 'databaseAccounts']);
        $account = $domain->databaseAccounts->first();
        $this->assertInstanceOf(DatabaseAccount::class, $account);
        $this->assertSame(DatabaseEngine::Mysql, $account->engine);

        $files = app(DokployDomainBackupService::class)->listRemoteFiles($domain);

        $this->assertCount(1, $files);
        $this->assertSame($path, $files[0]['Path']);
    }
}
