<?php

// DomainAliasTenantDatabaseProvisioner.php — Optional tenant DB on alias FQDN (multi-tenant Laravel).
//
// exports: DomainAliasTenantDatabaseProvisioner | provision(Domain $domain, string $databaseName): void
// used_by: app/Services/Hosting/DomainAliasManager.php
// rules:   CREATE DATABASE named like alias FQDN; GRANT existing site user only (no new user per alias).
//          Never drop tenant DB on alias delete. Site user must already exist on the primary domain.
// agent:   composer-2.5-fast | cursor | 2026-11-19 | s_alias_tenant_db | MariaDB tenant DB on alias create
// message:

namespace App\Services\Hosting;

use App\Enums\DatabaseEngine;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Validation\ValidationException;

class DomainAliasTenantDatabaseProvisioner
{
    public function __construct(private MysqlProvisioner $mysql) {}

    public function provision(Domain $domain, string $databaseName): void
    {
        $domain->loadMissing(['databaseAccounts', 'infrastructure']);

        $infrastructure = $domain->infrastructure;

        if ($infrastructure === null) {
            throw ValidationException::withMessages([
                'create_tenant_database' => __('panel.domains.aliases.tenant_database_no_infra'),
            ]);
        }

        $account = $domain->databaseAccounts
            ->first(fn (DatabaseAccount $item): bool => $item->engine === DatabaseEngine::Mysql);

        if (! $account instanceof DatabaseAccount) {
            throw ValidationException::withMessages([
                'create_tenant_database' => __('panel.domains.aliases.tenant_database_no_mysql'),
            ]);
        }

        if (
            DatabaseAccount::query()
                ->where('database_name', $databaseName)
                ->where('id', '!=', $account->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'create_tenant_database' => __('panel.domains.aliases.tenant_database_name_taken'),
            ]);
        }

        $this->mysql->createDatabaseIfNotExists($databaseName, $infrastructure);
        $this->mysql->grantExistingUserOnDatabase(
            $databaseName,
            $account->username,
            $account->privilege,
            $infrastructure,
        );
    }
}
