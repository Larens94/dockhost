<?php


// PostgresProvisioner.php — PostgresProvisioner module.
//
// exports: PostgresProvisioner | PostgresProvisioner::provision(string $database, string $username, string $password, ?Infrastructure $infrastructure = null): void | PostgresProvisioner::grantUser( string $database, string $username, string $password, DatabasePrivilege $privilege, ?Infrastructure $infrastructure = null, ): void
// used_by: app/Providers/AppServiceProvider.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         tests/Feature/DomainProvisionTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Services\Infra;

use App\Enums\DatabasePrivilege;
use App\Models\Infrastructure;
use Closure;
use InvalidArgumentException;
use PDO;

class PostgresProvisioner
{
    /**
     * @param  Closure(): PDO  $connector
     */
    public function __construct(private Closure $connector) {}

    public function provision(string $database, string $username, string $password, ?Infrastructure $infrastructure = null): void
    {
        $pdo = $infrastructure instanceof Infrastructure
            ? $this->connect($infrastructure)
            : ($this->connector)();
        $databaseIdentifier = $this->quoteIdentifier($database);
        $usernameIdentifier = $this->quoteIdentifier($username);
        $quotedPassword = $pdo->quote($password);

        $pdo->exec("CREATE USER {$usernameIdentifier} WITH PASSWORD {$quotedPassword}");
        $pdo->exec("CREATE DATABASE {$databaseIdentifier} OWNER {$usernameIdentifier}");
    }

    public function grantUser(
        string $database,
        string $username,
        string $password,
        DatabasePrivilege $privilege,
        ?Infrastructure $infrastructure = null,
    ): void {
        $pdo = $infrastructure instanceof Infrastructure
            ? $this->connect($infrastructure)
            : ($this->connector)();
        $databaseIdentifier = $this->quoteIdentifier($database);
        $usernameIdentifier = $this->quoteIdentifier($username);
        $quotedPassword = $pdo->quote($password);

        $pdo->exec("CREATE USER {$usernameIdentifier} WITH PASSWORD {$quotedPassword}");
        $pdo->exec("GRANT CONNECT ON DATABASE {$databaseIdentifier} TO {$usernameIdentifier}");

        if ($privilege === DatabasePrivilege::All) {
            $pdo->exec("GRANT ALL PRIVILEGES ON DATABASE {$databaseIdentifier} TO {$usernameIdentifier}");
        }

        $schemaPdo = $infrastructure instanceof Infrastructure
            ? $this->connect($infrastructure, $database)
            : $pdo;

        $schemaPdo->exec("GRANT USAGE ON SCHEMA public TO {$usernameIdentifier}");

        if ($privilege === DatabasePrivilege::Select) {
            $schemaPdo->exec("GRANT SELECT ON ALL TABLES IN SCHEMA public TO {$usernameIdentifier}");
        } else {
            $schemaPdo->exec("GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO {$usernameIdentifier}");
            $schemaPdo->exec("GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO {$usernameIdentifier}");
        }
    }

    private function connect(Infrastructure $infrastructure, ?string $database = null): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $infrastructure->postgres_host,
            $infrastructure->postgres_port,
            $database ?? $infrastructure->postgres_admin_database,
        );

        return new PDO(
            $dsn,
            $infrastructure->postgres_admin_user,
            $infrastructure->postgres_admin_password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function quoteIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid PostgreSQL identifier.');
        }

        return '"'.$name.'"';
    }
}
