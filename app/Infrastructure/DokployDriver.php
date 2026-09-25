<?php

namespace App\Infrastructure;

use App\Contracts\InfrastructureDriver;
use App\Support\GitRemote;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Dokploy API adapter.
 *
 * Live procedures (official REST, x-api-key):
 *   GET  /api/project.all
 *   GET  /api/server.all
 *   POST /api/application.create
 *   POST /api/application.saveEnvironment
 *   POST /api/application.saveGitProvider
 *   POST /api/application.deploy
 *   POST /api/application.stop
 *   POST /api/application.start
 *   GET  /api/application.one
 *   POST /api/application.delete
 *   POST /api/domain.create
 *   POST /api/compose.create
 *   POST /api/mariadb.create | postgres.create
 *
 * Without URL and API key the driver stays local: DockHost still records the tenant.
 */
class DokployDriver implements InfrastructureDriver
{
    public function name(): string
    {
        return 'dokploy';
    }

    public function ping(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'Dokploy URL or API key missing'];
        }

        try {
            $response = $this->request()->timeout(8)->get($this->url('project.all'));

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'connected' : 'HTTP '.$response->status(),
            ];
        } catch (Throwable $exception) {
            Log::warning('Dokploy ping failed', ['error' => $exception->getMessage()]);

            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }

    public function deployCompose(array $definition): array
    {
        if (! $this->configured()) {
            return [
                'external_id' => $this->localId((string) ($definition['name'] ?? 'compose')),
                'raw' => ['status' => 'local'],
            ];
        }

        $created = $this->post('compose.create', array_filter([
            'name' => $definition['name'] ?? 'compose',
            'environmentId' => $definition['environment_id'] ?? $this->environmentId(),
            'composeType' => 'docker-compose',
            'appName' => $definition['app_name'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $composeId = $created['composeId'] ?? $created['id'] ?? null;

        if (is_string($composeId) && $composeId !== '' && ! empty($definition['compose'])) {
            $this->post('compose.update', [
                'composeId' => $composeId,
                'composeFile' => $definition['compose'],
            ]);
            $this->post('compose.deploy', [
                'composeId' => $composeId,
            ]);
        }

        return [
            'external_id' => is_string($composeId) ? $composeId : null,
            'raw' => $created,
        ];
    }

    public function deployApplication(array $definition): array
    {
        if (! $this->configured()) {
            return [
                'external_id' => $this->localId((string) ($definition['domain'] ?? 'app')),
                'raw' => ['status' => 'local'],
            ];
        }

        $applicationId = $definition['application_id'] ?? null;
        $created = [];

        if (! is_string($applicationId) || $applicationId === '') {
            $created = $this->post('application.create', array_filter([
                'name' => $definition['name'] ?? $definition['domain'] ?? 'site',
                'appName' => $definition['app_name'] ?? null,
                'environmentId' => $definition['environment_id'] ?? $this->environmentId(),
                'description' => $definition['recipe'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));

            $applicationId = $created['applicationId'] ?? $created['id'] ?? null;
        }

        if (! is_string($applicationId) || $applicationId === '') {
            throw new RuntimeException('Dokploy application.create did not return an id.');
        }

        if (! empty($definition['env'])) {
            $this->updateEnvironment([
                'application_id' => $applicationId,
                'env' => $definition['env'],
            ]);
        }

        if (! empty($definition['repository'])) {
            $this->saveGitProvider([
                'application_id' => $applicationId,
                'repository' => $definition['repository'],
                'branch' => $definition['branch'] ?? 'main',
                'ssh_key_id' => $definition['ssh_key_id'] ?? null,
            ]);
        }

        $this->post('application.deploy', [
            'applicationId' => $applicationId,
        ]);

        return [
            'external_id' => $applicationId,
            'project_id' => $this->projectId($created),
            'environment_id' => $this->environmentIdFrom($created) ?: ($definition['environment_id'] ?? null),
            'raw' => $created,
        ];
    }

    public function saveGitProvider(array $definition): array
    {
        $applicationId = (string) ($definition['application_id'] ?? '');

        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        $git = GitRemote::forDokploy(
            (string) ($definition['repository'] ?? ''),
            (string) ($definition['branch'] ?? 'main'),
        );

        $saved = $this->post('application.saveGitProvider', array_filter([
            'applicationId' => $applicationId,
            'customGitUrl' => $git['url'],
            'customGitBranch' => $git['branch'],
            'customGitBuildPath' => '/',
            'customGitSSHKeyId' => $definition['ssh_key_id'] ?? null,
            'watchPaths' => [],
        ], fn ($value) => $value !== null && $value !== ''));

        return ['ok' => true, 'raw' => $saved];
    }

    public function stopApplication(string $applicationId): array
    {
        return $this->lifecycle('application.stop', $applicationId);
    }

    public function startApplication(string $applicationId): array
    {
        return $this->lifecycle('application.start', $applicationId);
    }

    public function applicationStatus(string $applicationId): array
    {
        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return [
                'ok' => true,
                'status' => 'done',
                'project_id' => null,
                'environment_id' => null,
            ];
        }

        $response = $this->request()->get($this->url('application.one'), [
            'applicationId' => $applicationId,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Dokploy application.one failed: HTTP {$response->status()}");
        }

        $json = $this->unwrap($response->json());
        $status = $json['applicationStatus'] ?? null;

        return [
            'ok' => true,
            'status' => is_string($status) ? $status : null,
            'project_id' => $this->projectId($json),
            'environment_id' => $this->environmentIdFrom($json),
            'raw' => $json,
        ];
    }

    public function attachDomain(array $definition): array
    {
        $applicationId = (string) ($definition['application_id'] ?? '');

        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        $created = $this->post('domain.create', [
            'host' => $definition['domain'],
            'path' => '/',
            'port' => (int) ($definition['port'] ?? 80),
            'https' => true,
            'certificateType' => 'letsencrypt',
            'applicationId' => $applicationId,
            'domainType' => 'application',
        ]);

        return ['ok' => true, 'raw' => $created];
    }

    public function createDatabase(array $definition): array
    {
        if (! $this->configured()) {
            return [
                'ok' => true,
                'external_id' => null,
                'status' => 'reserved',
                'raw' => ['status' => 'local'],
            ];
        }

        $engine = strtolower((string) ($definition['engine'] ?? 'mariadb'));
        [$procedure, $idKey] = match (true) {
            str_starts_with($engine, 'postgres') => ['postgres.create', 'postgresId'],
            str_starts_with($engine, 'mongo') => ['mongo.create', 'mongoId'],
            $engine === 'mysql' => ['mysql.create', 'mysqlId'],
            default => ['mariadb.create', 'mariadbId'],
        };

        $payload = [
            'name' => $definition['name'] ?? 'database',
            'appName' => $definition['app_name'] ?? null,
            'environmentId' => $definition['environment_id'] ?? $this->environmentId(),
            'databaseUser' => $definition['username'],
            'databasePassword' => $definition['password'],
            'dockerImage' => $definition['image'] ?? null,
        ];

        if (! str_starts_with($engine, 'mongo')) {
            $payload['databaseName'] = $definition['database'];
        }

        $created = $this->post($procedure, array_filter($payload, fn ($value) => $value !== null && $value !== ''));

        $id = $created[$idKey] ?? $created['id'] ?? null;

        return [
            'ok' => true,
            'external_id' => $id ? (string) $id : null,
            'status' => 'provisioned',
            'raw' => $created,
        ];
    }

    public function createCache(array $definition): array
    {
        $host = (string) ($definition['app_name'] ?? $definition['name'] ?? 'redis');

        if (! $this->configured()) {
            return [
                'ok' => true,
                'external_id' => null,
                'status' => 'reserved',
                'host' => $host,
                'raw' => ['status' => 'local'],
            ];
        }

        $created = $this->post('redis.create', array_filter([
            'name' => $definition['name'] ?? 'redis',
            'appName' => $definition['app_name'] ?? null,
            'environmentId' => $definition['environment_id'] ?? $this->environmentId(),
            'databasePassword' => $definition['password'],
            'dockerImage' => $definition['image'] ?? 'redis:7',
        ], fn ($value) => $value !== null && $value !== ''));

        $id = $created['redisId'] ?? $created['id'] ?? null;

        return [
            'ok' => true,
            'external_id' => $id ? (string) $id : null,
            'status' => 'provisioned',
            'host' => $host,
            'raw' => $created,
        ];
    }

    public function removeService(string $kind, string $id): array
    {
        if (! $this->configured() || $id === '' || str_starts_with($id, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        $normalized = strtolower($kind);

        if (str_starts_with($normalized, 'postgres')) {
            $normalized = 'postgres';
        } elseif (str_contains($normalized, 'maria')) {
            $normalized = 'mariadb';
        } elseif (str_starts_with($normalized, 'mongo')) {
            $normalized = 'mongo';
        }

        [$procedure, $key, $extra] = match ($normalized) {
            'redis' => ['redis.remove', 'redisId', []],
            'mongo' => ['mongo.remove', 'mongoId', []],
            'mysql' => ['mysql.remove', 'mysqlId', []],
            'postgres' => ['postgres.remove', 'postgresId', []],
            'mariadb' => ['mariadb.remove', 'mariadbId', []],
            'compose', 'minio', 'sftp', 'object', 'object-storage' => ['compose.delete', 'composeId', ['deleteVolumes' => true]],
            default => [null, null, []],
        };

        if ($procedure === null || $key === null) {
            return ['ok' => true, 'raw' => ['status' => 'skipped']];
        }

        $removed = $this->post($procedure, array_merge([$key => $id], $extra));

        return ['ok' => true, 'raw' => $removed];
    }

    public function deleteDomain(string $domainId): array
    {
        if (! $this->configured() || $domainId === '') {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        $removed = $this->post('domain.delete', [
            'domainId' => $domainId,
        ]);

        return ['ok' => true, 'raw' => $removed];
    }

    public function updateEnvironment(array $definition): array
    {
        $applicationId = (string) ($definition['application_id'] ?? '');

        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        $saved = $this->post('application.saveEnvironment', [
            'applicationId' => $applicationId,
            'env' => (string) ($definition['env'] ?? ''),
            'createEnvFile' => true,
        ]);

        return ['ok' => true, 'raw' => $saved];
    }

    public function destroyApplication(array $definition): array
    {
        $applicationId = (string) ($definition['application_id'] ?? '');

        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        try {
            $deleted = $this->post('application.delete', [
                'applicationId' => $applicationId,
            ]);

            return ['ok' => true, 'raw' => $deleted];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        }
    }

    public function listServers(): array
    {
        if (! $this->configured()) {
            return [
                'ok' => false,
                'message' => 'Dokploy URL or API key missing',
                'servers' => [],
            ];
        }

        try {
            $response = $this->request()->get($this->url('server.all'));

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'HTTP '.$response->status(),
                    'servers' => [],
                ];
            }

            $rows = $this->unwrap($response->json());

            if (isset($rows['servers']) && is_array($rows['servers'])) {
                $rows = $rows['servers'];
            }

            $servers = [];

            foreach (is_array($rows) ? $rows : [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $servers[] = [
                    'id' => (string) ($row['serverId'] ?? $row['id'] ?? ''),
                    'name' => (string) ($row['name'] ?? 'server'),
                    'ip' => isset($row['ipAddress']) ? (string) $row['ipAddress'] : (isset($row['ip']) ? (string) $row['ip'] : null),
                ];
            }

            return ['ok' => true, 'message' => 'synced', 'servers' => $servers];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => $exception->getMessage(), 'servers' => []];
        }
    }

    public function exec(array $definition): array
    {
        return [
            'ok' => false,
            'output' => 'Recorded only. Artisan runs in the site container; DockHost does not open a shell.',
        ];
    }

    private function configured(): bool
    {
        return (bool) config('dockhost.dokploy.url') && (bool) config('dockhost.dokploy.api_key');
    }

    private function environmentId(): string
    {
        $environmentId = (string) config('dockhost.dokploy.environment_id');

        if ($environmentId === '') {
            throw new RuntimeException('DOKPLOY_ENVIRONMENT_ID is required to create Dokploy resources.');
        }

        return $environmentId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $procedure, array $payload): array
    {
        $response = $this->request()->post($this->url($procedure), $payload);

        if (! $response->successful()) {
            throw new RuntimeException("Dokploy {$procedure} failed: HTTP {$response->status()}");
        }

        $json = $response->json();

        return is_array($json) ? $this->unwrap($json) : [];
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function unwrap(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        if (isset($json['result']['data'])) {
            $data = $json['result']['data'];

            if (is_array($data) && isset($data['json']) && is_array($data['json'])) {
                return $data['json'];
            }

            return is_array($data) ? $data : [];
        }

        return $json;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'x-api-key' => (string) config('dockhost.dokploy.api_key'),
            'Accept' => 'application/json',
        ])->timeout(20);
    }

    private function url(string $procedure): string
    {
        return rtrim((string) config('dockhost.dokploy.url'), '/').'/api/'.$procedure;
    }

    /**
     * @return array{ok: bool, raw?: mixed}
     */
    private function lifecycle(string $procedure, string $applicationId): array
    {
        if (! $this->configured() || $applicationId === '' || str_starts_with($applicationId, 'local_')) {
            return ['ok' => true, 'raw' => ['status' => 'local']];
        }

        return [
            'ok' => true,
            'raw' => $this->post($procedure, ['applicationId' => $applicationId]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function projectId(array $payload): ?string
    {
        $environment = is_array($payload['environment'] ?? null) ? $payload['environment'] : [];
        $project = is_array($environment['project'] ?? null) ? $environment['project'] : [];
        $id = $payload['projectId'] ?? $environment['projectId'] ?? $project['projectId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function environmentIdFrom(array $payload): ?string
    {
        $environment = is_array($payload['environment'] ?? null) ? $payload['environment'] : [];
        $id = $payload['environmentId'] ?? $environment['environmentId'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function localId(string $seed): string
    {
        return 'local_'.substr(sha1($seed), 0, 12);
    }
}
