<?php

// AuthorizesDomainHosting.php — Shared authorize helpers for domain-scoped FormRequests.
//
// exports: AuthorizesDomainHosting | domainFromRoute(): Domain | userCanMutateDomainHosting(): bool
// used_by: app/Http/Requests/StoreDomainDatabaseUserRequest.php
// rules:   readonly members MUST fail authorize (403) on mutate routes; admin bypasses pivot role.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Trait for domain role checks in FormRequests.

namespace App\Http\Requests\Concerns;

use App\Models\Domain;
use App\Models\User;

trait AuthorizesDomainHosting
{
    protected function domainFromRoute(): Domain
    {
        /** @var Domain $domain */
        $domain = $this->route('domain');

        return $domain;
    }

    protected function userCanMutateDomainHosting(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canMutateDomainHosting($this->domainFromRoute());
    }
}
