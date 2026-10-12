<?php

// DomainBackupManager.php — Create/restore/import domain database backups over the network (no Dokploy exec).
//
// exports: DomainBackupManager | DomainBackupManager::createDatabaseBackup(Domain $domain): DomainBackup | DomainBackupManager::restoreDatabaseBackup(DomainBackup $backup, ?Domain $into = null): void | DomainBackupManager::importSqlDump(Domain $domain, UploadedFile $file, bool $allowOverwriteProduction = false): DomainBackup
// used_by: app/Http/Controllers/DomainController.php
// rules:   DB dump via admin PDO on shared MariaDB — GRANT stays one-database for site users.
//          File/volume backup is recorded as kind=files with a clear “not yet wired” error until SFTP pull lands.
//          Never log SQL dump contents or passwords.
//          Import stores the uploaded dump as a DomainBackup then applies it into the domain DB name only.
//          Dump/restore/import use the site DB user (one-database GRANT) — never infra *.* for SQL apply.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_domain_backup | PDO SQL dump + restore for domain DB
//          composer-2.5-fast | cursor | 2026-10-11 | s_db_import | SQL dump upload import
//          composer-2.5-fast | cursor | 2026-10-11 | s_db_site_pdo | Site-user PDO for dump/import/restore
//          composer-2.5-fast | cursor | 2026-10-12 | s_db_import_parser | SqlDumpStatementSplitter for phpMyAdmin dumps
// message:

namespace App\Services\Hosting;

use App\Enums\DatabaseEngine;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\Infrastructure;
use App\Models\InfrastructureEnvironment;
use App\Services\Infra\MysqlCredentialHealer;
use App\Support\SqlDumpStatementSplitter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use Throwable;

class DomainBackupManager
{
    public function __construct(private MysqlCredentialHealer $healer) {}

    public function createDatabaseBackup(Domain $domain): DomainBackup
    {
        $account = $domain->databaseAccounts()->orderBy('id')->first();

        if (! $account instanceof DatabaseAccount || $account->engine !== DatabaseEngine::Mysql) {
            throw ValidationException::withMessages([
                'backup' => 'Questo dominio non ha un database MySQL da salvare.',
            ]);
        }

        $infrastructure = $account->infrastructure
            ?? Infrastructure::query()->where('slug', $account->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'backup' => 'Infrastruttura del database non trovata.',
            ]);
        }

        $backup = DomainBackup::query()->create([
            'domain_id' => $domain->id,
            'infrastructure_environment_id' => $domain->infrastructure_environment_id,
            'kind' => 'database',
            'status' => 'running',
            'meta' => [
                'database_name' => $account->database_name,
                'username' => $account->username,
            ],
        ]);

        try {
            $pdo = $this->sitePdo($account, $infrastructure);
            $sql = $this->dumpDatabase($pdo, $account->database_name);
            $relative = 'backups/domains/'.$domain->id.'/'.$backup->id.'-'.$account->database_name.'.sql';
            Storage::disk('local')->put($relative, $sql);

            $backup->update([
                'status' => 'completed',
                'disk_path' => $relative,
                'bytes' => strlen($sql),
                'error' => null,
            ]);
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'backup' => 'Backup database fallito: '.$exception->getMessage(),
            ]);
        }

        return $backup->refresh();
    }

    public function restoreDatabaseBackup(DomainBackup $backup, ?Domain $into = null): void
    {
        if ($backup->kind !== 'database' || $backup->status !== 'completed' || ! filled($backup->disk_path)) {
            throw ValidationException::withMessages([
                'backup' => 'Backup non ripristinabile (stato o tipo non validi).',
            ]);
        }

        $domain = $into ?? $backup->domain;

        if (! $domain instanceof Domain) {
            throw ValidationException::withMessages([
                'backup' => 'Dominio o account database assente per il restore.',
            ]);
        }

        $sql = Storage::disk('local')->get((string) $backup->disk_path);

        if (! is_string($sql) || $sql === '') {
            throw ValidationException::withMessages([
                'backup' => 'File di backup assente o vuoto.',
            ]);
        }

        $this->applySqlToDomain($domain, $sql, 'backup');
    }

    public function importSqlDump(
        Domain $domain,
        UploadedFile $file,
        bool $allowOverwriteProduction = false,
    ): DomainBackup {
        $account = $domain->databaseAccounts()->orderBy('id')->first();

        if (! $account instanceof DatabaseAccount || $account->engine !== DatabaseEngine::Mysql) {
            throw ValidationException::withMessages([
                'dump' => __('panel.domains.show.import_dump_no_mysql'),
            ]);
        }

        $this->assertProductionImportAllowed($domain, $allowOverwriteProduction);

        $sql = $this->readUploadedSql($file);

        if ($sql === '') {
            throw ValidationException::withMessages([
                'dump' => __('panel.domains.show.import_dump_empty'),
            ]);
        }

        $backup = DomainBackup::query()->create([
            'domain_id' => $domain->id,
            'infrastructure_environment_id' => $domain->infrastructure_environment_id,
            'kind' => 'database',
            'status' => 'running',
            'meta' => [
                'database_name' => $account->database_name,
                'username' => $account->username,
                'source' => 'import',
                'original_name' => $file->getClientOriginalName(),
            ],
        ]);

        try {
            $relative = 'backups/domains/'.$domain->id.'/'.$backup->id.'-import.sql';
            Storage::disk('local')->put($relative, $sql);

            $this->applySqlToDomain($domain, $sql, 'dump');

            $backup->update([
                'status' => 'completed',
                'disk_path' => $relative,
                'bytes' => strlen($sql),
                'error' => null,
            ]);
        } catch (ValidationException $exception) {
            $backup->update([
                'status' => 'failed',
                'error' => collect($exception->errors())->flatten()->first() ?: $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'dump' => __('panel.domains.show.import_dump_failed', ['error' => $exception->getMessage()]),
            ]);
        }

        return $backup->refresh();
    }

    private function assertProductionImportAllowed(Domain $domain, bool $allowOverwriteProduction): void
    {
        $env = $domain->infrastructureEnvironment;

        if ($env instanceof InfrastructureEnvironment
            && $env->is_default
            && $env->slug === 'production'
            && ! $allowOverwriteProduction
        ) {
            throw ValidationException::withMessages([
                'allow_overwrite_production' => __('panel.domains.show.import_dump_production_confirm'),
            ]);
        }
    }

    private function readUploadedSql(UploadedFile $file): string
    {
        $raw = file_get_contents($file->getRealPath() ?: '');

        if ($raw === false || $raw === '') {
            return '';
        }

        $name = strtolower($file->getClientOriginalName());

        if (str_ends_with($name, '.gz')) {
            $decoded = @gzdecode($raw);

            if ($decoded === false) {
                throw ValidationException::withMessages([
                    'dump' => __('panel.domains.show.import_dump_gz_invalid'),
                ]);
            }

            return $decoded;
        }

        return $raw;
    }

    private function applySqlToDomain(Domain $domain, string $sql, string $errorKey): void
    {
        $account = $domain->databaseAccounts()->orderBy('id')->first();
        $infrastructure = $account?->infrastructure
            ?? ($account ? Infrastructure::query()->where('slug', $account->infra_slug)->first() : null);

        if (! $account instanceof DatabaseAccount || ! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                $errorKey => 'Dominio o account database assente per il restore.',
            ]);
        }

        try {
            $pdo = $this->sitePdo($account, $infrastructure);

            foreach (SqlDumpStatementSplitter::statements($sql) as $statement) {
                if ($this->shouldSkipImportedStatement($statement)) {
                    continue;
                }

                $pdo->exec($statement);
            }
        } catch (PDOException $exception) {
            $message = $errorKey === 'dump'
                ? __('panel.domains.show.import_dump_failed', ['error' => $exception->getMessage()])
                : __('panel.domains.show.backup_restore_failed', ['error' => $exception->getMessage()]);

            throw ValidationException::withMessages([
                $errorKey => $message,
            ]);
        }
    }

    private function shouldSkipImportedStatement(string $statement): bool
    {
        return preg_match(
            '/^(CREATE DATABASE|USE|DROP DATABASE|CREATE USER|ALTER USER|DROP USER|GRANT|REVOKE|FLUSH\s+PRIVILEGES)\b/i',
            $statement,
        ) === 1;
    }

    private function sitePdo(DatabaseAccount $account, Infrastructure $infrastructure): PDO
    {
        $host = filled($account->host) ? (string) $account->host : (string) $infrastructure->mysql_host;
        $port = (int) ($account->port ?: $infrastructure->mysql_port ?: 3306);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $account->database_name,
        );

        try {
            return new PDO(
                $dsn,
                (string) $account->username,
                (string) $account->password_encrypted,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        } catch (PDOException $exception) {
            if (! str_contains($exception->getMessage(), '1045')
                && ! str_contains($exception->getMessage(), 'Access denied')) {
                throw $exception;
            }

            try {
                $this->healer->heal($infrastructure);
            } catch (ValidationException) {
                // Heal repairs infra admin; site-user 1045 is usually a wrong site password.
            }

            throw ValidationException::withMessages([
                'backup' => 'Accesso database sito negato per '.$account->username.'@'.$host.'. Re-sync utente o reset password dal pannello.',
            ]);
        }
    }

    private function dumpDatabase(PDO $pdo, string $database): string
    {
        $databaseIdentifier = '`'.str_replace('`', '``', $database).'`';
        $lines = [
            '-- DockHost domain backup',
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            "CREATE DATABASE IF NOT EXISTS {$databaseIdentifier};",
            "USE {$databaseIdentifier};",
        ];

        $tables = $pdo->query('SHOW TABLES FROM '.$databaseIdentifier)->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $tableName = (string) $table;
            $tableIdentifier = '`'.str_replace('`', '``', $tableName).'`';
            $create = $pdo->query('SHOW CREATE TABLE '.$databaseIdentifier.'.'.$tableIdentifier)->fetch(PDO::FETCH_ASSOC);
            $createSql = is_array($create) ? (string) ($create['Create Table'] ?? '') : '';

            if ($createSql === '') {
                continue;
            }

            $lines[] = 'DROP TABLE IF EXISTS '.$tableIdentifier.';';
            $lines[] = $createSql.';';

            $rows = $pdo->query('SELECT * FROM '.$databaseIdentifier.'.'.$tableIdentifier);
            while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map(
                    fn (string $column): string => '`'.str_replace('`', '``', $column).'`',
                    array_keys($row),
                );
                $values = array_map(function (mixed $value) use ($pdo): string {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return $pdo->quote((string) $value);
                }, array_values($row));

                $lines[] = 'INSERT INTO '.$tableIdentifier.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');';
            }
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines)."\n";
    }
}
