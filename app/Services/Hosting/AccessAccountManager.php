<?php


// AccessAccountManager.php — AccessAccountManager module.
//
// exports: AccessAccountManager | AccessAccountManager::generatedPassword(): string | AccessAccountManager::createDatabaseUserForDomain(Domain $domain, DatabasePrivilege $privilege): DatabaseAccount | AccessAccountManager::createDatabaseUserForInfrastructure( Infrastructure $infrastructure, DatabaseAccount $source, DatabasePrivilege $privilege, ): DatabaseAccount | AccessAccountManager::createSftpUserForDomain(Domain $domain): SftpUser | AccessAccountManager::createSftpUserForInfrastructure(Infrastructure $infrastructure, Domain $domain): SftpUser | AccessAccountManager::revealDatabase(DatabaseAccount $account, string $password): array | AccessAccountManager::revealInitialDomainAccess(Domain $domain): array | AccessAccountManager::revealSftp(SftpUser $user, string $password): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/InfrastructureController.php
//         tests/Feature/AccessAccountTest.php
// rules:   Passwords are generated once and returned via reveal*() for one-time flash — not stored for re-display.
//          SFTP passwords must be alphanumeric without ':' (atmoz users.conf). Never print secrets in logs.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | credential reveal rules 

namespace App\Services\Hosting;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\SftpUser;
use App\Models\StorageShare;
use App\Services\Infra\MysqlProvisioner;
use App\Services\Infra\PostgresProvisioner;
use App\Services\Infra\SftpDaemonSync;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccessAccountManager
{
    public function __construct(
        private MysqlProvisioner $mysqlProvisioner,
        private PostgresProvisioner $postgresProvisioner,
        private SftpDaemonSync $sftpDaemonSync,
    ) {}

    public static function generatedPassword(): string
    {
        return Str::password(24, symbols: false, spaces: false);
    }

    public function createDatabaseUserForDomain(Domain $domain, DatabasePrivilege $privilege): DatabaseAccount
    {
        $source = $domain->databaseAccounts()->orderBy('id')->first();

        if (! $source instanceof DatabaseAccount) {
            throw ValidationException::withMessages([
                'privilege' => 'Questo dominio non ha un database. Crealo prima di aggiungere utenti.',
            ]);
        }

        return $this->createDatabaseUser($source, $privilege);
    }

    public function resetDatabaseAccountPassword(DatabaseAccount $account): DatabaseAccount
    {
        $infrastructure = $account->infrastructure
            ?? Infrastructure::query()->where('slug', $account->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'database_account' => 'Infrastruttura del database non trovata.',
            ]);
        }

        $password = self::generatedPassword();

        if ($account->engine === DatabaseEngine::Mysql) {
            $this->mysqlProvisioner->replaceSiteUser(
                $account->database_name,
                $account->username,
                $password,
                $account->privilege,
                $infrastructure,
            );
        } elseif ($account->engine === DatabaseEngine::Postgres) {
            $this->postgresProvisioner->grantUser(
                $account->database_name,
                $account->username,
                $password,
                $account->privilege,
                $infrastructure,
            );
        }

        $account->update(['password_encrypted' => $password]);

        return $account->refresh();
    }

    public function deleteDatabaseAccount(DatabaseAccount $account): void
    {
        if ($account->domain_id !== null) {
            $count = DatabaseAccount::query()->where('domain_id', $account->domain_id)->count();

            if ($count < 2) {
                throw ValidationException::withMessages([
                    'database_account' => __('panel.domains.show.delete_db_user_last'),
                ]);
            }
        }

        $infrastructure = $account->infrastructure
            ?? Infrastructure::query()->where('slug', $account->infra_slug)->first();

        if ($account->engine === DatabaseEngine::Mysql && $infrastructure instanceof Infrastructure) {
            $this->mysqlProvisioner->dropSiteUser($account->username, $infrastructure);
        }

        $account->delete();
    }

    public function createDatabaseUserForInfrastructure(
        Infrastructure $infrastructure,
        DatabaseAccount $source,
        DatabasePrivilege $privilege,
    ): DatabaseAccount {
        if ((int) $source->infrastructure_id !== (int) $infrastructure->id && $source->infra_slug !== $infrastructure->slug) {
            throw ValidationException::withMessages([
                'database_account_id' => 'Il database non appartiene a questa infrastruttura.',
            ]);
        }

        return $this->createDatabaseUser($source, $privilege);
    }

    public function createSftpUserForDomain(Domain $domain): SftpUser
    {
        $infrastructure = $this->requireInfrastructure($domain);

        return $this->createSftpUser(
            $infrastructure,
            $domain,
            $this->domainHomePath($domain, $infrastructure),
        );
    }

    public function createSftpUserForInfrastructure(Infrastructure $infrastructure, Domain $domain): SftpUser
    {
        if ((int) $domain->infrastructure_id !== (int) $infrastructure->id) {
            throw ValidationException::withMessages([
                'domain_id' => 'Il dominio non appartiene a questa infrastruttura.',
            ]);
        }

        return $this->createSftpUser(
            $infrastructure,
            $domain,
            $this->domainHomePath($domain, $infrastructure),
        );
    }

    /**
     * @return array{username: string, password: string, kind: string, privilege?: string, database_name?: string, domain_id: int|null, infrastructure_id: int|null}
     */
    public function revealDatabase(DatabaseAccount $account, string $password): array
    {
        return [
            'kind' => 'database',
            'username' => $account->username,
            'password' => $password,
            'privilege' => $account->privilege->value,
            'database_name' => $account->database_name,
            'domain_id' => $account->domain_id,
            'infrastructure_id' => $account->infrastructure_id,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function revealInitialDomainAccess(Domain $domain): array
    {
        $domain->loadMissing(['databaseAccounts', 'sftpUsers']);

        $revealed = [];

        foreach ($domain->databaseAccounts as $account) {
            $revealed[] = $this->revealDatabase($account, $account->password_encrypted);
        }

        foreach ($domain->sftpUsers as $user) {
            $revealed[] = $this->revealSftp($user, $user->password_encrypted);
        }

        return $revealed;
    }

    /**
     * @return array{username: string, password: string, kind: string, home_path: string, domain_id: int|null, infrastructure_id: int|null}
     */
    public function revealSftp(SftpUser $user, string $password): array
    {
        return [
            'kind' => 'sftp',
            'username' => $user->username,
            'password' => $password,
            'home_path' => $user->home_path,
            'domain_id' => $user->domain_id,
            'infrastructure_id' => $user->infrastructure_id,
        ];
    }

    private function createDatabaseUser(DatabaseAccount $source, DatabasePrivilege $privilege): DatabaseAccount
    {
        $infrastructure = $source->infrastructure
            ?? Infrastructure::query()->where('slug', $source->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'privilege' => 'Infrastruttura del database non trovata.',
            ]);
        }

        $username = $this->uniqueDatabaseUsername($source);
        $password = self::generatedPassword();
        $engine = $source->engine;

        if ($engine === DatabaseEngine::Mysql) {
            $this->mysqlProvisioner->grantUser(
                $source->database_name,
                $username,
                $password,
                $privilege,
                $infrastructure,
            );
        } elseif ($engine === DatabaseEngine::Postgres) {
            $this->postgresProvisioner->grantUser(
                $source->database_name,
                $username,
                $password,
                $privilege,
                $infrastructure,
            );
        }

        return DatabaseAccount::query()->create([
            'domain_id' => $source->domain_id,
            'infrastructure_id' => $infrastructure->id,
            'engine' => $engine,
            'infra_slug' => $infrastructure->slug,
            'host' => $source->host,
            'port' => $source->port,
            'database_name' => $source->database_name,
            'username' => $username,
            'privilege' => $privilege,
            'password_encrypted' => $password,
        ]);
    }

    private function createSftpUser(Infrastructure $infrastructure, ?Domain $domain, string $homePath): SftpUser
    {
        $username = $this->nextSftpUsername($infrastructure, $domain);
        $password = self::generatedPassword();

        if (str_contains($password, ':')) {
            $password = self::generatedPassword();
        }

        $user = SftpUser::query()->create([
            'domain_id' => $domain?->id,
            'infrastructure_id' => $infrastructure->id,
            'username' => $username,
            'password_encrypted' => $password,
            'home_path' => $homePath,
        ]);

        $this->sftpDaemonSync->syncToContainer($user);

        return $user;
    }

    private function requireInfrastructure(Domain $domain): Infrastructure
    {
        $infrastructure = $domain->infrastructure
            ?? Infrastructure::query()->where('slug', $domain->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'username' => 'Infrastruttura del dominio non trovata.',
            ]);
        }

        return $infrastructure;
    }

    private function domainHomePath(Domain $domain, Infrastructure $infrastructure): string
    {
        $share = $domain->storageShares()->orderBy('id')->first();

        if ($share instanceof StorageShare && is_string($share->path) && $share->path !== '') {
            return $share->path;
        }

        $customerSlug = $domain->customer?->storageSlug() ?? 'shared';

        return rtrim($infrastructure->storage_root, '/').'/'.$customerSlug.'/'.$domain->fqdn;
    }

    private function uniqueDatabaseUsername(DatabaseAccount $source): string
    {
        $maxLength = $source->engine === DatabaseEngine::Postgres ? 63 : 32;
        $base = $this->identifier('u_'.$source->database_name, $maxLength - 5);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $username = $this->identifier($base.'_'.Str::lower(Str::random(4)), $maxLength);

            if (! DatabaseAccount::query()->where('username', $username)->exists()) {
                return $username;
            }
        }

        return $this->identifier($base.'_'.(string) time(), $maxLength);
    }

    private function nextSftpUsername(Infrastructure $infrastructure, ?Domain $domain = null): string
    {
        $source = $domain instanceof Domain ? 'sftp_'.$domain->fqdn : 'sftp_'.$infrastructure->slug;
        $base = $this->identifier($source, 27);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $username = $this->identifier($base.'_'.Str::lower(Str::random(4)), 32);

            if (! SftpUser::query()->where('username', $username)->exists()) {
                return $username;
            }
        }

        return $this->identifier($base.'_'.(string) time(), 32);
    }

    private function identifier(string $source, int $maxLength): string
    {
        $normalized = Str::lower(preg_replace('/[^A-Za-z0-9]+/', '_', $source) ?? '');
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            $normalized = 'user';
        }

        return Str::limit($normalized, $maxLength, '');
    }
}
