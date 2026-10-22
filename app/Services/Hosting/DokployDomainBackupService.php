<?php

// DokployDomainBackupService.php — Sync domain DB backups with Dokploy schedules and S3.
//
// exports: DokployDomainBackupService | DokployDomainBackupService::ensureSchedule(Domain $domain): void | DokployDomainBackupService::runManualBackup(Domain $domain): DomainBackup | DokployDomainBackupService::syncSettings(Domain $domain): void | DokployDomainBackupService::listRemoteFiles(Domain $domain): array | DokployDomainBackupService::restoreRemoteFile(Domain $domain, string $backupFile): void
// used_by: app/Services/Hosting/DomainBackupManager.php
//         app/Http/Controllers/DomainController.php
//         app/Console/Commands/ProvisionDokployDomainBackupsCommand.php
// rules:   One Dokploy backup schedule per domain DB on shared compose MariaDB. Never log passwords or dump contents.
//          backup.remove only when admin disables backups on domain — never on infra delete from this service alone.
// agent:   composer-2.5-fast | cursor | 2026-10-21 | s_dokploy_domain_backup | Dokploy API bridge for domain DB backups
// message:

namespace App\Services\Hosting;

use App\Enums\DatabaseEngine;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\DomainBackup;
use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use App\Support\DomainBackupSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DokployDomainBackupService
{
    public function __construct(private DokployClient $dokploy) {}

    public function isEnabled(): bool
    {
        return (bool) config('dokploy.domain_backups_enabled');
    }

    public function ensureSchedule(Domain $domain): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $context = $this->resolveContext($domain);

        if ($context === null) {
            return;
        }

        $payload = $this->schedulePayload($domain, $context);

        try {
            DB::transaction(function () use ($domain, $context, $payload): void {
                $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();

                $backupId = filled($locked->dokploy_backup_id)
                    ? (string) $locked->dokploy_backup_id
                    : $this->findExistingComposeBackupId($context, $payload);

                if ($backupId !== null) {
                    if (! filled($locked->dokploy_backup_id)) {
                        $locked->update(['dokploy_backup_id' => $backupId]);
                    }

                    $this->dokploy->updateBackup([
                        ...$payload,
                        'backupId' => $backupId,
                    ]);

                    return;
                }

                $created = $this->dokploy->createBackup($payload);

                try {
                    $newId = $this->dokploy->idFrom($created, 'backupId');
                } catch (Throwable) {
                    $newId = $this->findExistingComposeBackupId($context, $payload);
                }

                if (! is_string($newId) || $newId === '') {
                    throw new \RuntimeException('Dokploy did not return backupId after create.');
                }

                $locked->update(['dokploy_backup_id' => $newId]);
            });

            $domain->refresh();
        } catch (Throwable $exception) {
            $this->failBackup($exception);
        }
    }

    public function syncSettings(Domain $domain): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! $domain->backup_enabled) {
            $context = $this->resolveContext($domain);

            if ($context !== null && filled($domain->dokploy_backup_id)) {
                try {
                    $this->dokploy->updateBackup([
                        ...$this->schedulePayload($domain, $context),
                        'backupId' => (string) $domain->dokploy_backup_id,
                        'enabled' => false,
                    ]);
                } catch (Throwable) {
                    // Best-effort when disabling automatic backups.
                }
            }

            return;
        }

        $this->ensureSchedule($domain);
    }

    public function runManualBackup(Domain $domain): DomainBackup
    {
        $context = $this->requireContext($domain);

        $this->ensureSchedule($domain->refresh());

        if (! filled($domain->dokploy_backup_id)) {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_dokploy_not_configured'),
            ]);
        }

        $scheduleId = (string) $domain->dokploy_backup_id;

        $record = DomainBackup::query()->create([
            'domain_id' => $domain->id,
            'infrastructure_environment_id' => $domain->infrastructure_environment_id,
            'kind' => 'database',
            'storage_provider' => 'dokploy',
            'dokploy_backup_id' => $scheduleId,
            'status' => 'running',
            'meta' => [
                'database_name' => $context['database_name'],
                'prefix' => $this->backupPrefix($domain),
            ],
        ]);

        try {
            $this->dokploy->manualBackupCompose($scheduleId);
        } catch (Throwable $exception) {
            $record->update([
                'status' => 'failed',
                'error' => $this->dokploy->errorMessage($exception),
            ]);

            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_dokploy_failed', [
                    'error' => $this->dokploy->errorMessage($exception),
                ]),
            ]);
        }

        $domain->update(['backup_last_ran_at' => now()]);

        $latest = $this->pollLatestRemoteFile($domain, $context);

        $record->update([
            'status' => 'completed',
            'bytes' => is_int($latest['Size'] ?? null) ? $latest['Size'] : null,
            'error' => null,
            'meta' => [
                ...($record->meta ?? []),
                'backup_file' => $latest['Path'] ?? null,
                'backup_name' => $latest['Name'] ?? null,
                'triggered' => true,
            ],
        ]);

        return $record->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function storagePrefix(Domain $domain): string
    {
        return $this->backupPrefix($domain);
    }

    public function storageBrowsePrefix(Domain $domain): string
    {
        $context = $this->resolveContext($domain);

        if ($context === null) {
            return $this->backupPrefix($domain);
        }

        $searches = $this->s3DirectoryListingSearches($domain, $context);
        $panelPrefix = $this->backupPrefix($domain);

        foreach ($searches as $search) {
            if (str_contains(rtrim($search, '/'), $panelPrefix)) {
                return rtrim($search, '/');
            }
        }

        return $panelPrefix;
    }

    public function listRemoteFiles(Domain $domain): array
    {
        $context = $this->resolveContext($domain);

        if ($context === null) {
            return [];
        }

        return $this->discoverRemoteSqlFiles($domain, $context);
    }

    public function restoreRemoteFile(Domain $domain, string $backupFile): void
    {
        $context = $this->requireContext($domain);
        $backupFile = trim($backupFile);

        if ($backupFile === '') {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', ['error' => 'Backup file path is required.']),
            ]);
        }

        $composeId = $context['compose_id'];
        $rootPassword = $context['root_password'];

        if ($rootPassword === '') {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', [
                    'error' => 'MariaDB root password missing on infrastructure — heal credentials first.',
                ]),
            ]);
        }

        try {
            $this->dokploy->restoreComposeBackup([
                'databaseId' => $composeId,
                'backupType' => 'compose',
                'databaseType' => 'mariadb',
                'databaseName' => $context['database_name'],
                'backupFile' => $backupFile,
                'destinationId' => $context['destination_id'],
                'metadata' => [
                    'serviceName' => config('dokploy.compose_mariadb_service_name', 'mariadb'),
                    'mariadb' => [
                        'databaseUser' => 'root',
                        'databasePassword' => $rootPassword,
                    ],
                ],
            ]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', [
                    'error' => $this->dokploy->errorMessage($exception),
                ]),
            ]);
        }
    }

    public function restoreDomainBackup(DomainBackup $backup, Domain $domain): void
    {
        if ($backup->storage_provider !== 'dokploy') {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', ['error' => 'Legacy local backup — use panel import or restore file from list.']),
            ]);
        }

        $path = $backup->meta['backup_file'] ?? null;

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', ['error' => 'No S3 file path recorded for this backup run.']),
            ]);
        }

        $this->restoreRemoteFile($domain, $path);
    }

    /**
     * @return array{database_name: string, destination_id: string, compose_id: string, root_password: string}|null
     */
    private function resolveContext(Domain $domain): ?array
    {
        $account = $domain->databaseAccounts()->orderBy('id')->first();

        if (! $account instanceof DatabaseAccount || $account->engine !== DatabaseEngine::Mysql) {
            return null;
        }

        $infrastructure = $domain->infrastructure
            ?? Infrastructure::query()->where('slug', $domain->infra_slug)->first();

        if (! $infrastructure instanceof Infrastructure) {
            return null;
        }

        $destinationId = $infrastructure->dokploy_backup_destination_id;
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($destinationId) || $destinationId === ''
            || ! is_string($composeId) || $composeId === '') {
            return null;
        }

        return [
            'database_name' => (string) $account->database_name,
            'destination_id' => $destinationId,
            'compose_id' => $composeId,
            'root_password' => (string) ($infrastructure->mysql_root_password ?? ''),
        ];
    }

    /**
     * @return array{database_name: string, destination_id: string, compose_id: string, root_password: string}
     */
    private function requireContext(Domain $domain): array
    {
        $context = $this->resolveContext($domain);

        if ($context === null) {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_dokploy_not_configured'),
            ]);
        }

        $expected = $context['database_name'];
        $actual = $this->databaseName($domain);

        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'backup' => __('panel.domains.show.backup_restore_failed', ['error' => 'Database name mismatch.']),
            ]);
        }

        return $context;
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return array<string, mixed>
     */
    private function schedulePayload(Domain $domain, array $context): array
    {
        $serviceName = $this->mariadbServiceName();

        return [
            'schedule' => $this->cronFor($domain),
            'enabled' => (bool) $domain->backup_enabled,
            'prefix' => $this->backupPrefix($domain),
            'destinationId' => $context['destination_id'],
            'keepLatestCount' => $this->keepLatestCount($domain),
            'database' => $context['database_name'],
            'databaseType' => 'mariadb',
            'backupType' => 'compose',
            'composeId' => $context['compose_id'],
            'serviceName' => $serviceName,
            'metadata' => $this->composeMetadata($context),
        ];
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return array<string, mixed>
     */
    private function composeMetadata(array $context): array
    {
        $metadata = [
            'serviceName' => $this->mariadbServiceName(),
        ];

        if ($context['root_password'] !== '') {
            $metadata['mariadb'] = [
                'databaseUser' => 'root',
                'databasePassword' => $context['root_password'],
            ];
        }

        return $metadata;
    }

    private function mariadbServiceName(): string
    {
        return (string) config('dokploy.compose_mariadb_service_name', 'mariadb');
    }

    private function failBackup(Throwable $exception): void
    {
        throw ValidationException::withMessages([
            'backup' => __('panel.domains.show.backup_dokploy_failed', [
                'error' => $this->dokploy->errorMessage($exception),
            ]),
        ]);
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @param  array<string, mixed>  $payload
     */
    private function findExistingComposeBackupId(array $context, array $payload): ?string
    {
        try {
            $backups = $this->dokploy->backupsOnCompose($context['compose_id']);
        } catch (Throwable) {
            return null;
        }

        $wantedDatabase = (string) ($payload['database'] ?? '');
        $wantedPrefix = (string) ($payload['prefix'] ?? '');
        $wantedDestination = (string) ($payload['destinationId'] ?? '');

        $matches = [];

        foreach ($backups as $backup) {
            if (($backup['database'] ?? null) !== $wantedDatabase) {
                continue;
            }

            if (($backup['prefix'] ?? null) !== $wantedPrefix) {
                continue;
            }

            if ($wantedDestination !== ''
                && ($backup['destinationId'] ?? null) !== $wantedDestination) {
                continue;
            }

            $backupType = $backup['backupType'] ?? null;

            if ($backupType !== null && $backupType !== 'compose') {
                continue;
            }

            $id = $backup['backupId'] ?? $backup['id'] ?? null;

            if (is_string($id) && $id !== '') {
                $matches[] = $backup;
            }
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, fn (array $a, array $b): int => strcmp(
            (string) ($b['createdAt'] ?? $b['backupId'] ?? ''),
            (string) ($a['createdAt'] ?? $a['backupId'] ?? ''),
        ));

        $chosen = $matches[0];
        $id = $chosen['backupId'] ?? $chosen['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return array<string, mixed>
     */
    private function pollLatestRemoteFile(Domain $domain, array $context): array
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $latest = $this->latestRemoteFile($domain, $context);

            if ($latest !== []) {
                return $latest;
            }

            if ($attempt < 5) {
                sleep(2);
            }
        }

        return [];
    }

    private function backupPrefix(Domain $domain): string
    {
        $slug = Str::slug((string) ($domain->infra_slug ?: 'infra'), '-');
        $host = Str::lower((string) $domain->fqdn);

        return "{$slug}/{$host}";
    }

    private function cronFor(Domain $domain): string
    {
        return DomainBackupSchedule::normalize(
            is_string($domain->backup_schedule) ? $domain->backup_schedule : null,
            is_string($domain->backup_frequency) ? $domain->backup_frequency : null,
        );
    }

    private function keepLatestCount(Domain $domain): int
    {
        return max(1, min(365, (int) $domain->backup_retain_days));
    }

    private function databaseName(Domain $domain): string
    {
        $account = $domain->databaseAccounts()->orderBy('id')->first();

        if (! $account instanceof DatabaseAccount) {
            return '';
        }

        return (string) $account->database_name;
    }

    private function destinationId(Domain $domain): string
    {
        $infrastructure = $domain->infrastructure;

        return is_string($infrastructure?->dokploy_backup_destination_id)
            ? $infrastructure->dokploy_backup_destination_id
            : '';
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return array<string, mixed>
     */
    private function latestRemoteFile(Domain $domain, array $context): array
    {
        $sqlFiles = $this->discoverRemoteSqlFiles($domain, $context);

        return $sqlFiles[0] ?? [];
    }

    /**
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return list<array<string, mixed>>
     */
    private function discoverRemoteSqlFiles(Domain $domain, array $context): array
    {
        $fqdn = Str::lower((string) $domain->fqdn);
        $prefixLower = strtolower($this->backupPrefix($domain));
        $databaseName = strtolower($context['database_name']);
        $byPath = [];

        foreach ($this->s3DirectoryListingSearches($domain, $context) as $directory) {
            $this->mergeListedBackupEntries(
                $byPath,
                $this->listBackupDirectory($context['destination_id'], $directory),
            );
        }

        foreach ($byPath as $path => $file) {
            if (($file['IsDir'] ?? false) !== true) {
                continue;
            }

            $pathLower = strtolower((string) $path);

            if (! str_contains($pathLower, $fqdn)) {
                continue;
            }

            $this->mergeListedBackupEntries(
                $byPath,
                $this->listBackupDirectory($context['destination_id'], $path),
            );
        }

        $filtered = array_values(array_filter(
            $byPath,
            function (array $file) use ($fqdn, $databaseName, $prefixLower): bool {
                if (($file['IsDir'] ?? true) === true) {
                    return false;
                }

                $name = (string) ($file['Name'] ?? '');
                $path = strtolower((string) ($file['Path'] ?? $name));

                if ($name === '' && $path === '') {
                    return false;
                }

                $label = $name !== '' ? $name : $path;

                if (! $this->looksLikeSqlDump($label)) {
                    return false;
                }

                return str_contains($path, $fqdn)
                    || ($databaseName !== '' && str_contains($path, $databaseName))
                    || str_contains($path, $prefixLower);
            },
        ));

        usort($filtered, fn (array $a, array $b): int => strcmp(
            (string) ($b['Name'] ?? $b['Path'] ?? ''),
            (string) ($a['Name'] ?? $a['Path'] ?? ''),
        ));

        return $filtered;
    }

    /**
     * Dokploy stores compose DB dumps at `{composeAppName}_{service}/{backup.prefix}{file}` on S3.
     * listBackupFiles lists one directory level; the search string must end with `/` to list files inside the domain folder.
     *
     * @param  array{database_name: string, destination_id: string, compose_id: string, root_password: string}  $context
     * @return list<string>
     */
    private function s3DirectoryListingSearches(Domain $domain, array $context): array
    {
        $panelPrefix = $this->backupPrefix($domain);
        $searches = [
            $this->ensureTrailingSlash($panelPrefix),
            $this->ensureTrailingSlash(Str::before($panelPrefix, '/')),
        ];

        try {
            $compose = $this->dokploy->getCompose($context['compose_id']);
            $appName = $compose['appName'] ?? $compose['name'] ?? null;

            if (is_string($appName) && $appName !== '') {
                $serviceName = $this->mariadbServiceName();
                $s3Root = $serviceName !== '' ? "{$appName}_{$serviceName}" : $appName;
                $searches[] = $this->ensureTrailingSlash("{$s3Root}/{$panelPrefix}");
            }
        } catch (Throwable) {
            // Fall back to panel prefix searches when compose metadata is unavailable.
        }

        return array_values(array_unique(array_filter($searches)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listBackupDirectory(string $destinationId, string $directory): array
    {
        $directory = $this->ensureTrailingSlash(trim($directory));

        if ($directory === '') {
            return [];
        }

        try {
            return $this->dokploy->listBackupFiles($destinationId, $directory);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $byPath
     * @param  list<array<string, mixed>>  $batch
     */
    private function mergeListedBackupEntries(array &$byPath, array $batch): void
    {
        foreach ($batch as $file) {
            if (! is_array($file)) {
                continue;
            }

            $pathKey = is_string($file['Path'] ?? null)
                ? $file['Path']
                : (is_string($file['Name'] ?? null) ? $file['Name'] : null);

            if ($pathKey === null || $pathKey === '') {
                continue;
            }

            $byPath[$pathKey] = $file;
        }
    }

    private function ensureTrailingSlash(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '' : $path.'/';
    }

    private function looksLikeSqlDump(string $name): bool
    {
        $lower = strtolower($name);

        return str_contains($lower, '.sql')
            || str_ends_with($lower, '.sql.gz')
            || str_ends_with($lower, '.gz');
    }
}
