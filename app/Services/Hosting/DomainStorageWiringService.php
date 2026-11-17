<?php

// DomainStorageWiringService.php — Sync SFTP jail layout and Laravel start-command storage link for one domain.
//
// exports: DomainStorageWiringService | DomainStorageWiringService::panelState(Domain $domain): array | DomainStorageWiringService::wire(Domain $domain, bool $deployNow = false): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Services/Hosting/DomainProvisioner.php
// rules:   saveEnvironment + sftp-sync POST only — never delete Dokploy mounts/volumes. Never log passwords or env secrets.
// agent:   composer-2.5-fast | cursor | 2026-11-17 | s_storage_wire | Panel button + provision hook for SFTP/Laravel storage.

namespace App\Services\Hosting;

use App\Enums\DomainStack;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\SftpUser;
use App\Services\Dokploy\DokployApplicationAttacher;
use App\Services\Dokploy\DokployApplicationEnv;
use App\Services\Dokploy\DokployClient;
use App\Services\Infra\SftpDaemonSync;
use App\Support\LaravelStorageBootstrapShell;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainStorageWiringService
{
    public function __construct(
        private DokployClient $dokploy,
        private DokployApplicationEnv $envParser,
        private DokployApplicationAttacher $attacher,
        private SftpDaemonSync $sftpDaemonSync,
    ) {}

    /**
     * @return array{
     *     eligible: bool,
     *     needs_wiring: bool,
     *     is_laravel: bool,
     *     dokhosts_storage_path: string|null,
     *     sftp_upload_hint: string|null,
     *     error: string|null
     * }
     */
    public function panelState(Domain $domain): array
    {
        $domain->loadMissing(['dokployApplication', 'sftpUsers', 'infrastructure', 'storageShares']);

        $infrastructure = $domain->infrastructure;
        $hasSftp = $domain->sftpUsers->isNotEmpty();
        $isLaravel = ($domain->stack ?? DomainStack::None)->isLaravel();
        $applicationId = $domain->dokployApplication?->dokploy_application_id;
        $eligible = $infrastructure instanceof Infrastructure
            && $infrastructure->canHostDomains()
            && $hasSftp
            && (! $isLaravel || (is_string($applicationId) && $applicationId !== ''));

        if (! $eligible) {
            return [
                'eligible' => false,
                'needs_wiring' => false,
                'is_laravel' => $isLaravel,
                'dokhosts_storage_path' => null,
                'sftp_upload_hint' => null,
                'error' => null,
            ];
        }

        $storagePath = $this->storagePathHint($domain);
        $sftpHint = $this->sftpUploadHint($domain->sftpUsers->first());
        $configured = false;
        $error = null;

        if ($isLaravel && is_string($applicationId) && $applicationId !== '') {
            try {
                $application = $this->dokploy->getApplication($applicationId);
                $env = is_string($application['env'] ?? null) ? $application['env'] : '';
                $assignments = $this->envParser->assignments($env);
                $start = (string) ($assignments['NIXPACKS_START_CMD'] ?? '');
                $configured = LaravelStorageBootstrapShell::isConfigured($start);

                $pathFromEnv = trim((string) ($assignments['DOKHOSTS_STORAGE_PATH'] ?? ''));

                if ($pathFromEnv !== '') {
                    $storagePath = $pathFromEnv;
                }
            } catch (Throwable $exception) {
                $error = $this->dokploy->errorMessage($exception);
            }
        } else {
            $configured = false;
        }

        return [
            'eligible' => true,
            'needs_wiring' => $isLaravel ? ! $configured : true,
            'is_laravel' => $isLaravel,
            'dokhosts_storage_path' => $storagePath,
            'sftp_upload_hint' => $sftpHint,
            'error' => $error,
        ];
    }

    /**
     * @return array{sftp_synced: bool, env_updated: bool, deployed: bool, keys: list<string>}
     */
    public function wire(Domain $domain, bool $deployNow = false, bool $syncSftp = true): array
    {
        $domain->loadMissing(['dokployApplication', 'sftpUsers', 'infrastructure', 'storageShares']);

        $infrastructure = $domain->infrastructure;

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'storage' => 'Questo dominio non ha un’infrastruttura SFTP.',
            ]);
        }

        if ($domain->sftpUsers->isEmpty()) {
            throw ValidationException::withMessages([
                'storage' => 'Crea almeno un utente SFTP prima di collegare lo storage.',
            ]);
        }

        $isLaravel = ($domain->stack ?? DomainStack::None)->isLaravel();
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if ($isLaravel && (! is_string($applicationId) || $applicationId === '')) {
            throw ValidationException::withMessages([
                'storage' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        if ($syncSftp) {
            $this->sftpDaemonSync->writeUsersFile($infrastructure);
        }

        $envUpdated = false;
        $keys = [];

        if ($isLaravel) {
            $result = $this->attacher->applyLaravelDeployConfig($domain);
            $keys = array_values(array_unique([...$result['added'], ...$result['updated']]));
            $envUpdated = $keys !== [];
        }

        $deployed = false;

        if ($deployNow && is_string($applicationId) && $applicationId !== '') {
            try {
                $this->attacher->refreshNixpacksInstallCommand($domain);
                $this->dokploy->deploy(['applicationId' => $applicationId]);
                $deployed = true;
            } catch (Throwable $exception) {
                throw ValidationException::withMessages([
                    'storage' => $this->dokploy->errorMessage($exception),
                ]);
            }
        }

        return [
            'sftp_synced' => $syncSftp,
            'env_updated' => $envUpdated,
            'deployed' => $deployed,
            'keys' => $keys,
        ];
    }

    /**
     * Best-effort wiring after provision — never throws to the caller.
     */
    public function wireSilently(Domain $domain, bool $deployNow = false, bool $syncSftp = true): void
    {
        try {
            $this->wire($domain, $deployNow, $syncSftp);
        } catch (Throwable $exception) {
            Log::warning('domain.storage_wire_failed', [
                'domain_id' => $domain->id,
                'fqdn' => $domain->fqdn,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function storagePathHint(Domain $domain): ?string
    {
        $share = $domain->storageShares->first();

        if ($share !== null && filled($share->path)) {
            return $share->path;
        }

        $user = $domain->sftpUsers->first();

        if ($user instanceof SftpUser && filled($user->username) && $domain->infrastructure instanceof Infrastructure) {
            return rtrim($domain->infrastructure->storage_root, '/').'/'.$user->username;
        }

        return null;
    }

    private function sftpUploadHint(?SftpUser $user): ?string
    {
        if (! $user instanceof SftpUser) {
            return null;
        }

        return '/storage/app/public';
    }
}
