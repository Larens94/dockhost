<?php

// InfrastructureEnvironment.php — Dokploy environment under one Infrastructure project.
//
// exports: InfrastructureEnvironment | InfrastructureEnvironment::infrastructure(): BelongsTo | InfrastructureEnvironment::domains(): HasMany | InfrastructureEnvironment::backups(): HasMany
// used_by: app/Models/Domain.php
//         app/Models/DomainBackup.php
//         app/Services/Hosting/EnvironmentAligner.php
//         database/factories/InfrastructureEnvironmentFactory.php
// rules:   One infra project has many envs (production default). Each env may have its own compose/DB.
//          Site users stay user@'%' GRANT on one database only — never share prod DB with staging.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_infra_env | Multi-environment foundation
// message:

namespace App\Models;

use Database\Factories\InfrastructureEnvironmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'infrastructure_id',
    'name',
    'slug',
    'dokploy_environment_id',
    'dokploy_compose_id',
    'is_default',
])]
class InfrastructureEnvironment extends Model
{
    /** @use HasFactory<InfrastructureEnvironmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(DomainBackup::class);
    }
}
