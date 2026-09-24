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
            'composeFile' => $definition['compose'] ?? null,
            'appName' => $definition['app_name'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        return [
            'external_id' => $created['composeId'] ?? $created['id'] ?? null,
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

        $saved = $this->post('application.saveGitProvider', [
            'applicationId' => $applicationId,
            'customGitUrl' => $git['url'],
            'customGitBranch' => $git['branch'],
            'customGitBuildPath' => '/',
            'watchPaths' => [],
        ]);

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
        $procedure = str_starts_with($engine, 'postgres') ? 'postgres.create' : 'mariadb.create';

        $created = $this->post($procedure, array_filter([
            'name' => $definition['name'] ?? 'database',
            'appName' => $definition['app_name'] ?? null,
            'environmentId' => $definition['environment_id'] ?? $this->environmentId(),
            'databaseName' => $definition['database'],
            'databaseUser' => $definition['username'],
            'databasePassword' => $definition['password'],
            'dockerImage' => $definition['image'] ?? null,
        ], fn ($value) => $value !== null && $value !== ''));

        $id = $created['mariadbId'] ?? $created['postgresId'] ?? $created['id'] ?? null;

        return [
            'ok' => true,
            'external_id' => $id ? (string) $id : null,
            'status' => 'provisioned',
            'raw' => $created,
        ];
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
