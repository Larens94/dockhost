<?php

// DomainAliasKind.php — Alias vs subdomain hostname under a primary Domain.
//
// exports: DomainAliasKind | Alias | Subdomain | label(): string
// used_by: app/Models/DomainAlias.php | app/Http/Requests/StoreDomainAliasRequest.php
// rules:   subdomain fqdn is built as {label}.{primary}; alias is a full external hostname.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Kind enum for hosts
// message:

namespace App\Enums;

enum DomainAliasKind: string
{
    case Alias = 'alias';
    case Subdomain = 'subdomain';

    public function label(): string
    {
        return match ($this) {
            self::Alias => __('panel.domains.aliases.kind_alias'),
            self::Subdomain => __('panel.domains.aliases.kind_subdomain'),
        };
    }
}
