<?php

// MysqlProvisioner.php — Creates site DB + user on shared infra MariaDB.
//
// exports: MysqlProvisioner | MysqlProvisioner::provision(string $database, string $username, string $password, ?Infrastructure $infrastructure = null): void | MysqlProvisioner::grantUser( string $database, string $username, string $password, DatabasePrivilege $privilege, ?Infrastructure $infrastructure = null, ): void | MysqlProvisioner::replaceSiteUser( string $database, string $username, string $password, DatabasePrivilege $privilege, Infrastructure $infrastructure, ): void | MysqlProvisioner::dropSiteUser(string $username, Infrastructure $infrastructure): void | MysqlProvisioner::resyncDatabaseAccount(DatabaseAccount $account): void
// used_by: app/Providers/AppServiceProvider.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         tests/Feature/AccessAccountTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
//         tests/Unit/MysqlProvisionerConnectErrorTest.php
//         tests/Unit/MysqlProvisionerGrantTest.php
// rules:   Site users are user@'%' with GRANT only on that one database (db.*), NEVER *.* .
//          Connect via Infrastructure mysql_host hostname on dokploy-network (not an IP).
//          Admin compose user `infra` may keep broader grants from the stack; site accounts must stay scoped.
//          CREATE USER IF NOT EXISTS does not change an existing password. Always ALTER USER afterwards, or a retry keeps the old password and the app gets 1045.
//          Site passwords: IDENTIFIED BY 'plain' after SET old_passwords=0, so MariaDB stores a mysql_native_password hash that mysqli/phpMyAdmin accepts. IDENTIFIED BY PASSWORD(hash) is rejected by phpMyAdmin even when the panel PDO check passes. Verify login as the site user before returning success.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Codify one-DB GRANT for site users
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_db_pass | ALTER USER after CREATE so a retry replaces the password
//          composer-2.5-fast | cursor | 2026-10-09 | s_pma_native_pass | mysql_native_password for phpMyAdmin + resyncDatabaseAccount
//          composer-2.5-fast | cursor | 2026-10-09 | s_pma_identified_by | IDENTIFIED BY for mysqli/phpMyAdmin (not USING PASSWORD hook)
//          composer-2.5-fast | cursor | 2026-10-09 | s_pma_hash_verify | IDENTIFIED BY PASSWORD(hash) + post-grant login verify
//          grok-4.7 | cursor | 2026-10-09 | s_pma_plain_by | IDENTIFIED BY plain text so phpMyAdmin mysqli matches the stored hash
// message:

namespace App\Services\Infra;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Infrastructure;
use Closure;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PDO;
use PDOException;

class MysqlProvisioner
{
    /**
     * @param  Closure(): PDO  $connector
     * @param  (Closure(string, string, string): PDO)|null  $adminConnector
     */
    /**
     * @param  (Closure(string, string, string): PDO)|null  $siteUserLoginConnector
     */
    public function __construct(
        private Closure $connector,
        private ?ComposeMysqlCredentialAligner $aligner = null,
        private ?Closure $adminConnector = null,
        private ?Closure $siteUserLoginConnector = null,
    ) {}

    public function provision(string $database, string $username, string $password, ?Infrastructure $infrastructure = null): void
    {
        $pdo = $infrastructure instanceof Infrastructure
            ? $this->connect($infrastructure)
            : ($this->connector)();
        $databaseIdentifier = $this->quoteIdentifier($database);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$databaseIdentifier}");

        if (! $infrastructure instanceof Infrastructure) {
            throw new InvalidArgumentException('Infrastructure is required to provision a site database user.');
        }

        $this->replaceSiteUser($database, $username, $password, DatabasePrivilege::All, $infrastructure);
    }

    public function grantUser(
        string $database,
        string $username,
        string $password,
        DatabasePrivilege $privilege,
        ?Infrastructure $infrastructure = null,
    ): void {
        if (! $infrastructure instanceof Infrastructure) {
            throw new InvalidArgumentException('Infrastructure is required to grant a site database user.');
        }

        $this->replaceSiteUser($database, $username, $password, $privilege, $infrastructure);
    }

    public function replaceSiteUser(
        string $database,
        string $username,
        string $password,
        DatabasePrivilege $privilege,
        Infrastructure $infrastructure,
    ): void {
        $pdo = $this->connect($infrastructure);
        $databaseIdentifier = $this->quoteIdentifier($database);
        $usernameIdentifier = $this->quoteIdentifier($username);
        $grant = $privilege->mysqlGrant();
        $identified = $this->nativePasswordIdentifiedClause($pdo, $password);

        $this->dropAllUserHosts($pdo, $username);
        $pdo->exec("CREATE USER {$usernameIdentifier}@'%' {$identified}");
        $pdo->exec("ALTER USER {$usernameIdentifier}@'%' {$identified}");
        $pdo->exec("GRANT {$grant} ON {$databaseIdentifier}.* TO {$usernameIdentifier}@'%'");
        $pdo->exec('FLUSH PRIVILEGES');

        $this->assertSiteUserCanLogin($infrastructure, $username, $password);
    }

    public function dropSiteUser(string $username, Infrastructure $infrastructure): void
    {
        $pdo = $this->connect($infrastructure);
        $usernameIdentifier = $this->quoteIdentifier($username);

        $this->dropAllUserHosts($pdo, $username);
        $pdo->exec('FLUSH PRIVILEGES');
    }

    public function resyncDatabaseAccount(DatabaseAccount $account): void
    {
        if ($account->engine !== DatabaseEngine::Mysql) {
            throw new InvalidArgumentException('Not a MySQL database account.');
        }

        $infrastructure = $account->infrastructure
            ?? Infrastructure::query()->where('slug', $account->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw new InvalidArgumentException('Infrastructure not found for database account.');
        }

        $password = (string) $account->password_encrypted;

        if ($password === '') {
            throw new InvalidArgumentException('Database account has no stored password.');
        }

        $this->replaceSiteUser(
            $account->database_name,
            $account->username,
            $password,
            $account->privilege,
            $infrastructure,
        );
    }

    private function connect(Infrastructure $infrastructure): PDO
    {
        try {
            return $this->open($infrastructure);
        } catch (PDOException $exception) {
            if ($this->isAccessDenied($exception) && $this->aligner?->align($infrastructure)) {
                try {
                    return $this->open($infrastructure->refresh());
                } catch (PDOException $retry) {
                    throw $this->connectionValidationException($infrastructure, $retry);
                }
            }

            throw $this->connectionValidationException($infrastructure, $exception);
        }
    }

    private function open(Infrastructure $infrastructure): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $infrastructure->mysql_host,
            $infrastructure->mysql_port,
        );
        $username = (string) $infrastructure->mysql_admin_user;
        $password = (string) $infrastructure->mysql_admin_password;

        if ($this->adminConnector instanceof Closure) {
            return ($this->adminConnector)($dsn, $username, $password);
        }

        return new PDO(
            $dsn,
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function isAccessDenied(PDOException $exception): bool
    {
        return str_contains($exception->getMessage(), '1045')
            || str_contains($exception->getMessage(), 'Access denied');
    }

    private function connectionValidationException(Infrastructure $infrastructure, PDOException $exception): ValidationException
    {
        $slug = $infrastructure->slug;
        $host = $infrastructure->mysql_host;
        $message = 'Impossibile creare il database su '.$slug.' ('.$host.'): MariaDB ha rifiutato l’utente admin. '
            .'Le password del pannello e dello stack non coincidono, oppure l’host non è autorizzato. '
            .'Aggiorna lo stack da Infrastrutture e riprova.';

        if (! $this->isAccessDenied($exception)) {
            $message = 'Impossibile raggiungere MariaDB su '.$slug.' ('.$host.'). '
                .'Verifica che lo stack sia deployato sulla rete Dokploy e riprova.';
        }

        return ValidationException::withMessages([
            'create_database' => $message,
            'engine' => $message,
        ]);
    }

    private function nativePasswordIdentifiedClause(PDO $pdo, string $password): string
    {
        $pdo->exec('SET old_passwords=0');

        return 'IDENTIFIED BY '.$pdo->quote($password);
    }

    private function dropAllUserHosts(PDO $pdo, string $username): void
    {
        $usernameIdentifier = $this->quoteIdentifier($username);
        $statement = $pdo->prepare('SELECT Host FROM mysql.global_priv WHERE User = ?');
        $statement->execute([$username]);

        while (($host = $statement->fetchColumn()) !== false) {
            $quotedHost = $pdo->quote((string) $host);
            $pdo->exec("DROP USER IF EXISTS {$usernameIdentifier}@{$quotedHost}");
        }
    }

    private function assertSiteUserCanLogin(Infrastructure $infrastructure, string $username, string $password): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $infrastructure->mysql_host,
            $infrastructure->mysql_port,
        );

        try {
            if ($this->siteUserLoginConnector instanceof Closure) {
                ($this->siteUserLoginConnector)($dsn, $username, $password);

                return;
            }

            new PDO(
                $dsn,
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $exception) {
            throw $this->siteUserLoginValidationException($infrastructure, $exception);
        }
    }

    private function siteUserLoginValidationException(Infrastructure $infrastructure, PDOException $exception): ValidationException
    {
        $message = 'MariaDB ha salvato l’utente ma il login con la password generata è fallito su '
            .$infrastructure->slug.' ('.$infrastructure->mysql_host.'). '
            .'Aggiorna il pannello e lo stack infra, poi usa «Reimposta password» o «Risincronizza utenti MySQL».';

        if (! $this->isAccessDenied($exception)) {
            $message = 'Impossibile verificare il login MySQL dell’utente su '
                .$infrastructure->slug.' ('.$infrastructure->mysql_host.'). Riprova.';
        }

        return ValidationException::withMessages([
            'privilege' => $message,
            'database_account' => $message,
            'create_database' => $message,
            'engine' => $message,
        ]);
    }

    private function quoteIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new InvalidArgumentException('Invalid MySQL identifier.');
        }

        return '`'.$name.'`';
    }
}
