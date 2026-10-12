<?php

// DomainAlias.php — Extra hostname (alias or subdomain) for a primary Domain.
//
// exports: DomainAlias | DomainAlias::domain(): BelongsTo
// used_by: app/Services/Hosting/DomainAliasManager.php | app/Http/Controllers/DomainController.php
// rules:   Multi-tenancy: web_service without redirect_301 keeps Host = alias on the same Dokploy app.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | DomainAlias model
// message:

namespace App\Models;

use App\Enums\DomainAliasKind;
use Database\Factories\DomainAliasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'domain_id',
    'fqdn',
    'kind',
    'web_service',
    'redirect_301',
    'mail_service',
    'sync_dns',
    'dokploy_domain_id',
])]
class DomainAlias extends Model
{
    /** @use HasFactory<DomainAliasFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DomainAliasKind::class,
            'web_service' => 'boolean',
            'redirect_301' => 'boolean',
            'mail_service' => 'boolean',
            'sync_dns' => 'boolean',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
