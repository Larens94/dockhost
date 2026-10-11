<?php

// EnvironmentAligner.php — Copy database contents from a source domain env into a target domain env.
//
// exports: EnvironmentAligner | EnvironmentAligner::alignDatabase(Domain $source, Domain $target): DomainBackup
// used_by: app/Http/Controllers/DomainController.php
// rules:   Target must not be the default production env when source is production-aligned carelessly —
//          caller must pass confirm. Never copy Dokploy app secrets (APP_KEY, OAuth). DB only in v1.
//          Uses DomainBackupManager dump of source then restore into target database name.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_env_align | Align DB from source domain to target
// message:

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\InfrastructureEnvironment;
use Illuminate\Validation\ValidationException;

class EnvironmentAligner
{
    public function __construct(private DomainBackupManager $backups) {}

    /**
     * Dump source domain DB and restore into the target domain's database.
     */
    public function alignDatabase(Domain $source, Domain $target, bool $allowOverwriteProduction = false): DomainBackup
    {
        if ((int) $source->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'align' => 'Sorgente e destinazione devono essere domini diversi (es. prod → staging).',
            ]);
        }

        $targetEnv = $target->infrastructureEnvironment;

        if ($targetEnv instanceof InfrastructureEnvironment
            && $targetEnv->is_default
            && $targetEnv->slug === 'production'
            && ! $allowOverwriteProduction
        ) {
            throw ValidationException::withMessages([
                'align' => 'Rifiuto overwrite di production senza conferma esplicita.',
            ]);
        }

        $backup = $this->backups->createDatabaseBackup($source);
        $this->backups->restoreDatabaseBackup($backup, $target);

        $backup->update([
            'meta' => [
                ...($backup->meta ?? []),
                'aligned_into_domain_id' => $target->id,
                'aligned_into_fqdn' => $target->fqdn,
            ],
        ]);

        return $backup->refresh();
    }
}
