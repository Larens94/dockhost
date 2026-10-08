<?php


// DatabaseEngine.php — DatabaseEngine module.
//
// exports: DatabaseEngine | DatabaseEngine::Mysql | DatabaseEngine::Postgres
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Requests/StoreDomainDatabaseRequest.php
//         app/Http/Requests/StoreDomainRequest.php
//         app/Models/DatabaseAccount.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/DatabaseAccountFactory.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Enums;

enum DatabaseEngine: string
{
    case Mysql = 'mysql';
    case Postgres = 'postgres';
}
