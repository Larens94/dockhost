<?php

// ProvisionDokployDomainBackupsCommand.php — Ensure Dokploy backup schedules exist for domain databases.
//
// exports: ProvisionDokployDomainBackupsCommand | ProvisionDokployDomainBackupsCommand::handle(DokployDomainBackupService $backups): int
// used_by: bootstrap/app.php (schedule)
// rules:   Does not run local PDO dumps. Never log secrets.
// agent:   composer-2.5-fast | cursor | 2026-10-21 | s_dokploy_domain_backup | Sync Dokploy backup schedules
// message:

namespace App\Console\Commands;

use App\Enums\DatabaseEngine;
use App\Models\Domain;
use App\Services\Hosting\DokployDomainBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('domains:provision-dokploy-backups {--domain= : Limit to one domain id}')]
#[Description('Ensure Dokploy S3 backup schedules match panel domain backup settings')]
class ProvisionDokployDomainBackupsCommand extends Command
{
    public function handle(DokployDomainBackupService $backups): int
    {
        if (! $backups->isEnabled()) {
            $this->warn('DOKPLOY_DOMAIN_BACKUPS is disabled — nothing to provision.');

            return self::SUCCESS;
        }

        $query = Domain::query()
            ->whereHas('databaseAccounts', fn ($accounts) => $accounts->where('engine', DatabaseEngine::Mysql))
            ->orderBy('id');

        if (filled($this->option('domain'))) {
            $query->whereKey((int) $this->option('domain'));
        }

        $synced = 0;
        $failed = 0;

        foreach ($query->cursor() as $domain) {
            try {
                $backups->syncSettings($domain);
                $synced++;
                $this->info("Synced backup schedule for {$domain->fqdn}.");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Failed for {$domain->fqdn}: ".$exception->getMessage());
            }
        }

        $this->info("Provisioned: {$synced}, failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
