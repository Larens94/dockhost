<?php

// DomainProvisioner.php — DomainProvisioner module.
//
// exports: DomainProvisioner | DomainProvisioner::provision(Subscription $subscription, array $attributes): Domain | DomainProvisioner::provisionDatabase(Domain $domain, DatabaseEngine $engine, ?string $infraSlug = null): DatabaseAccount | DomainProvisioner::attachLaravel(Domain $domain, array $attributes = []): DokployApplication | DomainProvisioner::attachApplication(Domain $domain, array $attributes = []): DokployApplication | DomainProvisioner::decommission(Domain $domain): void
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/SubscriptionController.php
// rules:   Orchestrates Domain + DB + storage + DokployApplication attach — not raw Compose edits.
//          DB CREATE goes to shared infra engines (Mysql/PostgresProvisioner), never mariadb.create per site.
//          Site DB users: user@'%' GRANT on that one database only (never *.*). Attach uses dokploy-network hostnames.
//          Site DB passwords are [A-Za-z0-9] only. Symbols such as # and $ are stripped or expanded when Dokploy injects the env, and migrate then gets 1045.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | hosting orchestration rules
//          composer | cursor | 2026-09-21 | s_20260921_shared_net | GRANT + shared-network attach rules
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_db_pass | Site DB passwords are alphanumeric so env injection keeps them intact
// agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Space destroy decommissions each domain.

namespace App\Services\Hosting;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Enums\DomainStack;
use App\Models\DatabaseAccount;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\SftpUser;
use App\Models\StorageShare;
use App\Models\Subscription;
use App\Services\Dokploy\DokployApplicationAttacher;
use App\Services\Dokploy\DokployClient;
use App\Services\Infra\MysqlProvisioner;
use App\Services\Infra\PostgresProvisioner;
use App\Services\Infra\SftpDaemonSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainProvisioner
{
    public function __construct(
        private MysqlProvisioner $mysqlProvisioner,
        private PostgresProvisioner $postgresProvisioner,
        private SftpDaemonSync $sftpDaemonSync,
        private DokployApplicationAttacher $dokployApplicationAttacher,
        private DokployClient $dokploy,
    ) {}

    /**
     * @param  array{
     *     fqdn: string,
     *     create_database?: bool,
     *     engine?: string|null,
     *     infra_slug: string,
     *     stack?: string
     * }  $attributes
     */
    public function provision(Subscription $subscription, array $attributes): Domain
    {
        if (! $subscription->canAddDomain()) {
            throw ValidationException::withMessages([
                'fqdn' => 'This space has reached the domain limit for its plan.',
            ]);
        }

        $fqdn = Str::lower($attributes['fqdn']);
        $infrastructure = $this->resolveAssignable($attributes['infra_slug'] ?? null, 'infra_slug');
        $createDatabase = (bool) ($attributes['create_database'] ?? false);
        $stack = DomainStack::from($attributes['stack'] ?? DomainStack::None->value);

        if (! $infrastructure->canHostDomains()) {
            throw ValidationException::withMessages([
                'infra_slug' => 'Questa infrastruttura non ha SFTP. Usa uno stack creato da DokHosts.',
            ]);
        }

        return DB::transaction(function () use ($subscription, $attributes, $fqdn, $infrastructure, $createDatabase, $stack): Domain {
            $domain = Domain::query()->create([
                'customer_id' => $subscription->customer_id,
                'subscription_id' => $subscription->id,
                'infrastructure_id' => $infrastructure->id,
                'fqdn' => $fqdn,
                'infra_slug' => $infrastructure->slug,
                'stack' => $stack,
            ]);

            if ($createDatabase) {
                $engine = DatabaseEngine::from($attributes['engine'] ?? DatabaseEngine::Mysql->value);
                $this->createDatabaseAccount($domain, $engine, $infrastructure);
            }

            $this->createStorageAndSftp($domain, $subscription, $fqdn, $infrastructure);

            if ($stack->createsApplication()) {
                $this->attachApplication($domain);
            }

            return $domain->load(['databaseAccounts', 'storageShares', 'sftpUsers', 'dokployApplication']);
        });
    }

    public function provisionDatabase(Domain $domain, DatabaseEngine $engine, ?string $infraSlug = null): DatabaseAccount
    {
        if ($domain->databaseAccounts()->exists()) {
            throw ValidationException::withMessages([
                'engine' => 'This domain already has a database.',
            ]);
        }

        $infrastructure = $this->resolveAssignable(
            $infraSlug ?? $domain->infra_slug,
            $infraSlug !== null ? 'infra_slug' : 'engine',
        );

        return $this->createDatabaseAccount($domain, $engine, $infrastructure);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachLaravel(Domain $domain, array $attributes = []): DokployApplication
    {
        $domain->forceFill(['stack' => DomainStack::Laravel])->save();

        return $this->attachApplication(
            $domain->fresh(['infrastructure', 'databaseAccounts', 'storageShares', 'customer']),
            $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function attachApplication(Domain $domain, array $attributes = []): DokployApplication
    {
        if ($domain->dokployApplication()->exists()) {
            throw ValidationException::withMessages([
                'stack' => 'This domain already has a Dokploy application.',
            ]);
        }

        $domain->loadMissing('infrastructure');

        if (! ($domain->stack ?? DomainStack::None)->createsApplication()) {
            throw ValidationException::withMessages([
                'stack' => 'Questo dominio è solo hosting: scegli uno stack applicativo.',
            ]);
        }

        return $this->dokployApplicationAttacher->attach($domain, $attributes);
    }

    public function decommission(Domain $domain): void
    {
        $domain->load(['dokployApplication', 'infrastructure']);
        $infrastructure = $domain->infrastructure
            ?? Infrastructure::query()->find($domain->infrastructure_id);
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (is_string($applicationId) && $applicationId !== '') {
            try {
                $this->dokploy->deleteApplication([
                    'applicationId' => $applicationId,
                ]);
            } catch (Throwable) {
            }
        }

        $domain->delete();

        if ($infrastructure instanceof Infrastructure) {
            $this->sftpDaemonSync->writeUsersFile($infrastructure);
        }
    }

    private function createDatabaseAccount(
        Domain $domain,
        DatabaseEngine $engine,
        Infrastructure $infrastructure,
    ): DatabaseAccount {
        if ($engine === DatabaseEngine::Mysql && ! $infrastructure->canProvisionMysql()) {
            throw ValidationException::withMessages([
                'engine' => 'MariaDB non è disponibile su '.$infrastructure->slug.'. Scegli uno stack deployato con MariaDB.',
            ]);
        }

        if ($engine === DatabaseEngine::Postgres && ! $infrastructure->canProvisionPostgres()) {
            throw ValidationException::withMessages([
                'engine' => 'Postgres non è disponibile su '.$infrastructure->slug.'. Scegli uno stack deployato con Postgres.',
            ]);
        }

        $databaseName = $this->identifier('d_'.$domain->fqdn, $engine === DatabaseEngine::Postgres ? 63 : 64);
        $databaseUsername = $this->identifier('u_'.$domain->fqdn, $engine === DatabaseEngine::Postgres ? 63 : 32);
        $databasePassword = Str::password(24, symbols: false);

        if ($engine === DatabaseEngine::Mysql) {
            $this->mysqlProvisioner->provision($databaseName, $databaseUsername, $databasePassword, $infrastructure);
        } elseif ($engine === DatabaseEngine::Postgres) {
            $this->postgresProvisioner->provision($databaseName, $databaseUsername, $databasePassword, $infrastructure);
        }

        $host = $engine === DatabaseEngine::Postgres
            ? $infrastructure->postgres_host
            : $infrastructure->mysql_host;
        $port = $engine === DatabaseEngine::Postgres
            ? $infrastructure->postgres_port
            : $infrastructure->mysql_port;

        return DatabaseAccount::query()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'engine' => $engine,
            'infra_slug' => $infrastructure->slug,
            'host' => $host,
            'port' => $port,
            'database_name' => $databaseName,
            'username' => $databaseUsername,
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => $databasePassword,
        ]);
    }

    private function resolveAssignable(?string $slug, string $field): Infrastructure
    {
        if (! is_string($slug) || $slug === '') {
            throw ValidationException::withMessages([
                $field => 'Scegli un’infrastruttura creata da DokHosts.',
            ]);
        }

        $infrastructure = Infrastructure::query()->panelManaged()->where('slug', $slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                $field => 'Solo gli stack creati da DokHosts si possono assegnare. infra-old e compose non gestiti restano fuori.',
            ]);
        }

        return $infrastructure;
    }

    private function createStorageAndSftp(
        Domain $domain,
        Subscription $subscription,
        string $fqdn,
        Infrastructure $infrastructure,
    ): void {
        $customer = $subscription->customer;
        $storageRoot = $infrastructure->storage_root;
        $storagePath = rtrim($storageRoot, '/').'/'.$customer->storageSlug().'/'.$fqdn;

        config([
            'infra.storage_root' => $infrastructure->storage_root,
            'infra.sftp.users_file' => $infrastructure->sftp_users_file,
        ]);

        StorageShare::query()->create([
            'domain_id' => $domain->id,
            'path' => $storagePath,
        ]);

        $sftpUser = SftpUser::query()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'username' => $this->identifier('sftp_'.$fqdn, 32),
            'password_encrypted' => AccessAccountManager::generatedPassword(),
            'home_path' => $storagePath,
        ]);

        $this->sftpDaemonSync->syncToContainer($sftpUser);
    }

    private function identifier(string $source, int $maxLength): string
    {
        $normalized = Str::lower(preg_replace('/[^A-Za-z0-9]+/', '_', $source) ?? '');
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            $normalized = 'site';
        }

        return Str::limit($normalized, $maxLength, '');
    }
}
