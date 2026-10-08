<?php


// DatabasePrivilege.php — DatabasePrivilege module.
//
// exports: DatabasePrivilege | DatabasePrivilege::All | DatabasePrivilege::Select | DatabasePrivilege::mysqlGrant(): string
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/InfrastructureController.php
//         app/Http/Requests/StoreDomainDatabaseUserRequest.php
//         app/Http/Requests/StoreInfrastructureDatabaseUserRequest.php
//         app/Models/DatabaseAccount.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         app/Services/Infra/MysqlProvisioner.php
//         app/Services/Infra/PostgresProvisioner.php
//         database/factories/DatabaseAccountFactory.php
//         tests/Feature/AccessAccountTest.php
//         tests/Unit/MysqlProvisionerGrantTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Enums;

enum DatabasePrivilege: string
{
    case All = 'all';
    case Select = 'select';

    public function mysqlGrant(): string
    {
        return $this === self::Select ? 'SELECT' : 'ALL PRIVILEGES';
    }
}
