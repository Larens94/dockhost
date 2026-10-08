<?php


// SftpDaemonSync.php — SftpDaemonSync module.
//
// exports: SftpDaemonSync | SftpDaemonSync::syncToContainer(SftpUser $user): void | SftpDaemonSync::writeUsersFile(?Infrastructure $infrastructure, ?SftpUser $fallbackUser = null): void
// used_by: app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         tests/Unit/SftpDaemonSyncTest.php
// rules:   POST to {slug}-sftp-sync:8787 only — panel MUST NOT mount infra {slug}_data volumes.
//          Never log Bearer sftp_sync_token or user passwords.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | sidecar + secrets rules 

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Models\SftpUser;
use App\Services\Dokploy\DokployClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SftpDaemonSync
{
    public function __construct(private DokployClient $dokploy) {}

    /**
     * Push users.conf and storage directories to the infra sidecar (not the panel filesystem).
     */
    public function syncToContainer(SftpUser $user): void
    {
        $infrastructure = $this->resolveInfrastructure($user);

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'username' => 'Infrastruttura SFTP non trovata.',
            ]);
        }

        $this->writeUsersFile($infrastructure, $user);
    }

    public function writeUsersFile(?Infrastructure $infrastructure, ?SftpUser $fallbackUser = null): void
    {
        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'username' => 'Infrastruttura SFTP non trovata.',
            ]);
        }

        $token = (string) ($infrastructure->sftp_sync_token ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'username' => 'Questa infrastruttura non ha ancora il sidecar SFTP. Usa «Aggiorna stack» e riprova.',
            ]);
        }

        $query = SftpUser::query()->orderBy('id')->where('infrastructure_id', $infrastructure->id);

        $users = $query->get();

        if ($users->isEmpty() && $fallbackUser instanceof SftpUser) {
            $users = collect([$fallbackUser]);
        }

        $payload = [
            'users' => $users
                ->map(fn (SftpUser $sftpUser): array => [
                    'username' => $sftpUser->username,
                    'password' => $sftpUser->password_encrypted,
                ])
                ->values()
                ->all(),
            'directories' => $this->directoriesFor($infrastructure, $users),
        ];

        try {
            Http::timeout(15)
                ->withToken($token)
                ->acceptJson()
                ->post($infrastructure->sftpSyncUrl(), $payload)
                ->throw();
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'username' => 'Il sidecar SFTP di '.$infrastructure->slug.' ha rifiutato il sync. Aggiorna lo stack e verifica che sftp-sync sia su.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'username' => 'Impossibile raggiungere '.$infrastructure->slug.'-sftp-sync. Lo stack deve essere deployato sulla stessa rete Dokploy del pannello.',
            ]);
        }

        $this->restartSftpDaemon($infrastructure);
    }

    /**
     * @param  Collection<int, SftpUser>  $users
     * @return list<string>
     */
    private function directoriesFor(Infrastructure $infrastructure, $users): array
    {
        $paths = [];

        foreach ($users as $user) {
            $paths[] = $this->containerPath($infrastructure, $user->home_path);
            $paths[] = '/data/'.$user->username;
        }

        return array_values(array_unique(array_filter($paths)));
    }

    private function containerPath(Infrastructure $infrastructure, string $path): string
    {
        $slugPrefix = '/data/'.$infrastructure->slug.'/';

        if (str_starts_with($path, $slugPrefix)) {
            return '/data/'.substr($path, strlen($slugPrefix));
        }

        if (str_starts_with($path, '/data/')) {
            return $path;
        }

        return '/data/'.ltrim($path, '/');
    }

    private function restartSftpDaemon(Infrastructure $infrastructure): void
    {
        if (! filled($infrastructure->dokploy_compose_id)) {
            return;
        }

        try {
            $payload = $this->dokploy->containersByAppName($infrastructure->slug);
        } catch (Throwable) {
            return;
        }

        $containerId = $this->sftpContainerId($payload);

        if (! is_string($containerId) || $containerId === '') {
            return;
        }

        try {
            $this->dokploy->restartContainer($containerId);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'username' => 'Utenti scritti, ma il container SFTP di '.$infrastructure->slug.' non si è riavviato. Riavvialo da Dokploy.',
            ]);
        }
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function sftpContainerId(array $payload): ?string
    {
        $rows = $payload;

        if ($payload !== [] && ! array_is_list($payload)) {
            foreach (['result.data', 'data', 'containers', 'items'] as $path) {
                $nested = data_get($payload, $path);

                if (is_array($nested) && array_is_list($nested)) {
                    $rows = $nested;

                    break;
                }
            }
        }

        foreach ($rows as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = strtolower(ltrim((string) ($item['Name'] ?? $item['name'] ?? ''), '/'));

            if ($name === '' || str_contains($name, 'sftp-sync') || str_contains($name, 'sftp-users-init')) {
                continue;
            }

            if (! str_contains($name, 'sftp')) {
                continue;
            }

            $rawId = ltrim((string) ($item['Id'] ?? $item['ID'] ?? $item['id'] ?? $item['containerId'] ?? ''), '/');

            foreach ([$rawId, $name] as $candidate) {
                if ($candidate !== '' && preg_match('/^[a-zA-Z0-9.\-_]+$/', $candidate) === 1) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function resolveInfrastructure(SftpUser $user): ?Infrastructure
    {
        if ($user->infrastructure instanceof Infrastructure) {
            return $user->infrastructure;
        }

        if ($user->infrastructure_id) {
            return Infrastructure::query()->find($user->infrastructure_id);
        }

        $user->loadMissing('domain.infrastructure');

        return $user->domain?->infrastructure;
    }
}
