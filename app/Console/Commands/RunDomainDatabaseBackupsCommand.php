<?php

// RunDomainDatabaseBackupsCommand.php — Scheduled / on-demand domain database backups.
//
// exports: RunDomainDatabaseBackupsCommand | RunDomainDatabaseBackupsCommand::handle(DomainBackupManager $backups): int
// used_by: routes/console.php (schedule)
// rules:   Only domains with backup_enabled + at least one MySQL account. Prune by backup_retain_days.
//          Never log dump contents or passwords.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_auto_backup | Daily/weekly DB backups per domain
// message:

namespace App\Console\Commands;

use App\Enums\DatabaseEngine;
use App\Models\Domain;
use App\Models\DomainBackup;
use App\Services\Hosting\DomainBackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('domains:run-database-backups {--domain= : Limit to one domain id} {--force : Ignore schedule window}')]
#[Description('Crea i backup database automatici per i domini abilitati e applica la retention')]
class RunDomainDatabaseBackupsCommand extends Command
{
    public function handle(DomainBackupManager $backups): int
    {
        if (config('dokploy.domain_backups_enabled')) {
            $this->info('Dokploy domain backups enabled — automatic dumps run on Dokploy; skipping local PDO backups.');

            return self::SUCCESS;
        }

        $query = Domain::query()
            ->where('backup_enabled', true)
            ->whereHas('databaseAccounts', fn ($accounts) => $accounts->where('engine', DatabaseEngine::Mysql))
            ->orderBy('id');

        if (filled($this->option('domain'))) {
            $query->whereKey((int) $this->option('domain'));
        }

        $ran = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->cursor() as $domain) {
            if (! $this->option('force') && ! $this->isDue($domain)) {
                $skipped++;
                $this->prune($domain);

                continue;
            }

            try {
                $backup = $backups->createDatabaseBackup($domain);
                $domain->update(['backup_last_ran_at' => now()]);
                $ran++;
                $this->info("Backup #{$backup->id} per {$domain->fqdn} ({$backup->status}).");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Backup fallito per {$domain->fqdn}: ".$exception->getMessage());
            }

            $this->prune($domain);
        }

        $this->info("Completati: {$ran}, saltati: {$skipped}, falliti: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isDue(Domain $domain): bool
    {
        $last = $domain->backup_last_ran_at;

        if ($last === null) {
            return true;
        }

        $frequency = $domain->backup_frequency ?: 'daily';

        if ($frequency === 'weekly') {
            return $last->lte(now()->subWeek());
        }

        return $last->lte(now()->subDay());
    }

    private function prune(Domain $domain): void
    {
        $retainDays = max(1, (int) $domain->backup_retain_days);

        $old = DomainBackup::query()
            ->where('domain_id', $domain->id)
            ->where('kind', 'database')
            ->where('created_at', '<', now()->subDays($retainDays))
            ->orderBy('id')
            ->get();

        foreach ($old as $backup) {
            if (filled($backup->disk_path)) {
                Storage::disk('local')->delete((string) $backup->disk_path);
            }

            $backup->delete();
        }
    }
}
