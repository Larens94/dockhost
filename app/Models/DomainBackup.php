<?php

// DomainBackup.php — Stored backup artifact for one domain (DB and/or files).
//
// exports: DomainBackup | DomainBackup::domain(): BelongsTo | DomainBackup::infrastructureEnvironment(): BelongsTo
// used_by: app/Services/Hosting/DomainBackupManager.php
//         database/factories/DomainBackupFactory.php
// rules:   disk_path is under storage/app/backups — never expose absolute host paths to non-admins.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_domain_backup | Domain-scoped backup records
// message:

namespace App\Models;

use Database\Factories\DomainBackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'domain_id',
    'infrastructure_environment_id',
    'kind',
    'status',
    'disk_path',
    'bytes',
    'error',
    'meta',
])]
class DomainBackup extends Model
{
    /** @use HasFactory<DomainBackupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'meta' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function infrastructureEnvironment(): BelongsTo
    {
        return $this->belongsTo(InfrastructureEnvironment::class);
    }
}
