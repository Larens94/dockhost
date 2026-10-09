<?php

// DokployClient.php — DokployClient module.
//
// exports: DokployClient | DokployClient::createApplication(array $payload): array | DokployClient::saveEnvironment(array $payload): array | DokployClient::deploy(array $payload): array | DokployClient::createDomain(array $payload): array | DokployClient::createComposeDomain(array $payload): array | DokployClient::saveGitProvider(array $payload): array | DokployClient::allProjects(): array | DokployClient::getProject(string $projectId): array | DokployClient::composeIdsFromProject(array $project): array | DokployClient::createProject(array $payload): array | DokployClient::deleteProject(array $payload): array | DokployClient::deleteCompose(array $payload): array | DokployClient::stopCompose(array $payload): array | DokployClient::cleanUnusedVolumes(?string $serverId = null): array | DokployClient::createEnvironment(array $payload): array | DokployClient::createNetwork(array $payload): array | DokployClient::listNetworks(): array | DokployClient::updateApplication(array $payload): array | DokployClient::createCompose(array $payload): array | (+19 more)
// used_by: app/Services/Hosting/DomainProvisioner.php
//         app/Services/Infra/ComposeDeployStatus.php
//         app/Services/Infra/ComposeMysqlCredentialAligner.php
//         app/Services/Infra/ComposeRuntimeInspector.php
//         app/Services/Infra/InfraDataNetworks.php
//         app/Services/Infra/InfrastructureProvisioner.php
//         app/Services/Infra/SftpDaemonSync.php
//         app/Services/Laravel/LaravelToolkitExecutor.php
//         tests/Feature/DokployClientTest.php
//         tests/Feature/LaravelToolkitTest.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_toolkit_terminal | Messaggi workflow terminale; hook futuro httpExecIsAvailable().
// agent:   grok-4.7 | cursor | 2026-10-09 | s_github_source | Name-match containers when the label list has none running.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Services\Dokploy;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class DokployClient
{
    /**
     * Live Dokploy has no HTTP exec: POST docker.executeCommand / application.executeCommand
     * and docker.exec return 404 (tRPC NOT_FOUND). Container commands use WebSocket
     * /docker-container-terminal from the dashboard (session auth, not x-api-key).
     * When Dokploy adds a documented x-api-key exec procedure, probe it here and flip httpExecIsAvailable().
     */
    public const HTTP_EXEC_UNAVAILABLE_MESSAGE = 'Dokploy non espone exec via API — usa il terminale.';

    public const TERMINAL_WORKFLOW_OVERVIEW_MESSAGE = 'Dokploy non esegue comandi via API su questa istanza. Usa i pulsanti sotto: copiano il comando e aprono l’application su Dokploy (General → Open Terminal, poi incolla).';

    public const TERMINAL_WORKFLOW_RESULT_MESSAGE = 'Comando copiato negli appunti. Su Dokploy: General → Open Terminal, incolla ed esegui.';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createApplication(array $payload): array
    {
        return $this->post('application.create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveEnvironment(array $payload): array
    {
        return $this->post('application.saveEnvironment', [
            'buildArgs' => '',
            'buildSecrets' => '',
            'createEnvFile' => false,
            ...$payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deploy(array $payload): array
    {
        return $this->post('application.deploy', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createDomain(array $payload): array
    {
        return $this->post('domain.create', $payload);
    }

    /**
     * Compose service hostname (phpMyAdmin / pgAdmin / MinIO). Traefik HTTP-01 needs host :80;
     * `port` is the container port Dokploy routes to (80 for PMA/PGA, 9001 for MinIO console).
     *
     * @param  array{host: string, composeId: string, serviceName: string, port: int}  $payload
     * @return array<string, mixed>
     */
    public function createComposeDomain(array $payload): array
    {
        return $this->createDomain([
            'host' => $payload['host'],
            'path' => '/',
            'port' => $payload['port'],
            'https' => true,
            'certificateType' => 'letsencrypt',
            'domainType' => 'compose',
            'composeId' => $payload['composeId'],
            'serviceName' => $payload['serviceName'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveGitProvider(array $payload): array
    {
        return $this->post('application.saveGitProvider', $payload);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function allProjects(): array
    {
        $payload = $this->get('project.all');

        if ($payload === [] || array_is_list($payload)) {
            return $payload;
        }

        foreach (['result.data', 'data', 'projects'] as $path) {
            $nested = data_get($payload, $path);

            if (is_array($nested) && array_is_list($nested)) {
                return $nested;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProject(string $projectId): array
    {
        return $this->get('project.one', ['projectId' => $projectId]);
    }

    /**
     * @param  array<string, mixed>  $project
     * @return list<string>
     */
    public function composeIdsFromProject(array $project): array
    {
        $ids = [];
        $environments = $project['environments'] ?? data_get($project, 'result.data.environments') ?? [];

        if (! is_array($environments)) {
            return [];
        }

        foreach ($environments as $environment) {
            if (! is_array($environment)) {
                continue;
            }

            foreach (['compose', 'composes'] as $key) {
                $services = $environment[$key] ?? [];

                if (! is_array($services)) {
                    continue;
                }

                foreach ($services as $compose) {
                    if (! is_array($compose)) {
                        continue;
                    }

                    $composeId = $compose['composeId'] ?? $compose['id'] ?? null;

                    if (is_string($composeId) && $composeId !== '') {
                        $ids[] = $composeId;
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createProject(array $payload): array
    {
        return $this->post('project.create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deleteProject(array $payload): array
    {
        return $this->post('project.remove', $payload, 120);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deleteCompose(array $payload): array
    {
        return $this->post('compose.delete', $payload, 120);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function stopCompose(array $payload): array
    {
        return $this->post('compose.stop', $payload, 120);
    }

    /**
     * @return array<string, mixed>
     */
    public function cleanUnusedVolumes(?string $serverId = null): array
    {
        return $this->post('settings.cleanUnusedVolumes', filled($serverId) ? ['serverId' => $serverId] : [], 120);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createEnvironment(array $payload): array
    {
        return $this->post('environment.create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createNetwork(array $payload): array
    {
        return $this->post('network.create', $payload);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listNetworks(): array
    {
        $payload = $this->get('network.all');

        if ($payload === [] || array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['result.data', 'data', 'networks'] as $path) {
            $nested = data_get($payload, $path);

            if (is_array($nested) && array_is_list($nested)) {
                return array_values(array_filter($nested, is_array(...)));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateApplication(array $payload): array
    {
        return $this->post('application.update', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createCompose(array $payload): array
    {
        return $this->post('compose.create', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateCompose(array $payload): array
    {
        return $this->post('compose.update', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function saveComposeEnvironment(array $payload): array
    {
        return $this->post('compose.saveEnvironment', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deployCompose(array $payload): array
    {
        return $this->post('compose.deploy', $payload, 120);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompose(string $composeId): array
    {
        return $this->get('compose.one', ['composeId' => $composeId]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function composeDeployments(string $composeId): array
    {
        return $this->get('deployment.allByCompose', ['composeId' => $composeId]);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function containersByAppName(string $appName, string $appType = 'docker-compose'): array
    {
        return $this->get('docker.getContainersByAppNameMatch', [
            'appName' => $appName,
            'appType' => $appType,
        ]);
    }

    /**
     * Standalone application containers (Laravel Toolkit), then compose-name match.
     *
     * @return list<array<string, mixed>>
     */
    public function applicationContainers(string $appName): array
    {
        $labeled = [];

        try {
            $labeled = $this->containerList($this->get('docker.getContainersByAppLabel', [
                'appName' => $appName,
                'type' => 'standalone',
            ]));
        } catch (Throwable) {
        }

        if ($this->containsRunningContainer($labeled)) {
            return $labeled;
        }

        try {
            $matched = $this->containerList($this->get('docker.getContainersByAppNameMatch', [
                'appName' => $appName,
            ]));
        } catch (Throwable) {
            return $labeled;
        }

        return $this->mergeContainerLists($labeled, $matched);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function applicationDeployments(string $applicationId): array
    {
        return $this->get('deployment.all', ['applicationId' => $applicationId]);
    }

    public function httpExecIsAvailable(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function restartContainer(string $containerId): array
    {
        return $this->post('docker.restartContainer', ['containerId' => $containerId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function deleteApplication(array $payload): array
    {
        return $this->post('application.delete', $payload, 120);
    }

    public function readComposeLogs(
        string $composeId,
        string $containerId,
        int $tail = 300,
        string $since = 'all',
        ?string $search = null,
    ): string {
        $query = [
            'composeId' => $composeId,
            'containerId' => $containerId,
            'tail' => $tail,
            'since' => $since,
        ];

        if (is_string($search) && $search !== '') {
            $query['search'] = $search;
        }

        $response = $this->http(30)->get('/compose.readLogs', $query)->throw();
        $json = $response->json();

        if ($json === null) {
            return $response->body();
        }

        return $this->stringifyLogs($json);
    }

    /**
     * @return array<string, mixed>
     */
    public function getApplication(string $applicationId): array
    {
        return $this->get('application.one', ['applicationId' => $applicationId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getGitlabIntegration(string $gitlabId): array
    {
        return $this->get('gitlab.one', ['gitlabId' => $gitlabId]);
    }

    /**
     * @return list<array{mountId: string|null, mountPath: string, volumeName: string|null}>
     */
    public function applicationMounts(string $applicationId): array
    {
        $mounts = $this->getApplication($applicationId)['mounts'] ?? [];

        if (! is_array($mounts)) {
            return [];
        }

        $normalized = [];

        foreach ($mounts as $mount) {
            if (! is_array($mount)) {
                continue;
            }

            $path = $mount['mountPath'] ?? null;

            if (! is_string($path) || $path === '') {
                continue;
            }

            $mountId = $mount['mountId'] ?? $mount['id'] ?? null;
            $volumeName = $mount['volumeName'] ?? null;

            $normalized[] = [
                'mountId' => is_string($mountId) && $mountId !== '' ? $mountId : null,
                'mountPath' => $path,
                'volumeName' => is_string($volumeName) && $volumeName !== '' ? $volumeName : null,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function applicationMountPaths(string $applicationId): array
    {
        return array_column($this->applicationMounts($applicationId), 'mountPath');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createMount(array $payload): array
    {
        return $this->post('mounts.create', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function removeMount(string $mountId): array
    {
        return $this->post('mounts.remove', ['mountId' => $mountId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function idFrom(array $payload, string $key): string
    {
        foreach ([
            $payload[$key] ?? null,
            data_get($payload, 'application.'.$key),
            data_get($payload, 'project.'.$key),
            data_get($payload, 'environment.'.$key),
            $key === 'applicationId' ? ($payload['id'] ?? null) : null,
            data_get($payload, 'result.data.'.$key),
            data_get($payload, 'result.data.project.'.$key),
            data_get($payload, 'result.data.environment.'.$key),
            $key === 'projectId' ? ($payload['id'] ?? null) : null,
            $key === 'environmentId' ? ($payload['id'] ?? null) : null,
            $key === 'composeId' ? ($payload['id'] ?? null) : null,
            $key === 'networkId' ? ($payload['id'] ?? null) : null,
            data_get($payload, 'network.'.$key),
        ] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        throw new \RuntimeException("Dokploy did not return {$key}.");
    }

    public function errorMessage(Throwable $exception): string
    {
        $raw = $this->rawErrorMessage($exception);

        if ($this->isMissingProcedure($exception, $raw)) {
            return 'Dokploy non espone questa procedura HTTP (404). Per i comandi nel container usa il terminale su Dokploy.';
        }

        if (str_contains($raw, 'container name') && str_contains($raw, 'already in use')) {
            if (preg_match('/container name "([^"]+)"/', $raw, $matches) === 1) {
                return 'Esiste già un container Docker con nome «'.$matches[1].'» da un deploy precedente. '
                    .'Elimina lo stack Dokploy vecchio (stesso slug) oppure rimuovi il container orfano, poi riprova.';
            }

            return 'Esiste già un container Docker con lo stesso nome da un deploy precedente. '
                .'Elimina lo stack Dokploy vecchio (stesso slug) oppure rimuovi i container orfani, poi riprova.';
        }

        return $raw;
    }

    private function rawErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            $json = $exception->response->json();

            foreach (['message', 'error', 'msg'] as $key) {
                $value = data_get($json, $key);

                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            $body = $exception->response->body();

            if (is_string($body) && trim($body) !== '') {
                return Str::limit(trim($body), 500);
            }
        }

        return $exception->getMessage();
    }

    private function isMissingProcedure(Throwable $exception, string $raw): bool
    {
        if (! $exception instanceof RequestException || $exception->response->status() !== 404) {
            return false;
        }

        $code = data_get($exception->response->json(), 'code');

        return strcasecmp($raw, 'Not found') === 0
            || (is_string($code) && strcasecmp($code, 'NOT_FOUND') === 0);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $procedure, array $payload, int $timeout = 15): array
    {
        return $this->jsonArray(
            $this->http($timeout)->post('/'.$procedure, $payload)->throw()->json(),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int|string, mixed>
     */
    private function get(string $procedure, array $query = [], int $timeout = 15): array
    {
        return $this->jsonArray($this->getJson($procedure, $query, $timeout));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function getJson(string $procedure, array $query = [], int $timeout = 15): mixed
    {
        return $this->http($timeout)->get('/'.$procedure, $query)->throw()->json();
    }

    private function stringifyLogs(mixed $json): string
    {
        if (is_string($json)) {
            return $json;
        }

        if (! is_array($json)) {
            return '';
        }

        foreach (['logs', 'data', 'result.data', 'message'] as $path) {
            $value = data_get($json, $path);

            if (is_string($value)) {
                return $value;
            }
        }

        if ($json !== [] && array_is_list($json) && array_filter($json, is_string(...)) === $json) {
            return implode("\n", $json);
        }

        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
    }

    /**
     * @param  list<array<string, mixed>>  $containers
     */
    private function containsRunningContainer(array $containers): bool
    {
        foreach ($containers as $container) {
            if ($this->containerLooksRunning($container)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerLooksRunning(array $container): bool
    {
        $stateValue = $container['State'] ?? $container['state'] ?? null;
        $state = is_array($stateValue)
            ? strtolower((string) ($stateValue['Status'] ?? $stateValue['status'] ?? ''))
            : strtolower(trim((string) $stateValue));

        if ($state === 'running' || $state === 'healthy' || str_starts_with($state, 'running')) {
            return true;
        }

        $status = strtolower((string) ($container['Status'] ?? $container['status'] ?? ''));

        return str_starts_with($status, 'up') || str_contains($status, 'running');
    }

    /**
     * @param  list<array<string, mixed>>  $primary
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    private function mergeContainerLists(array $primary, array $extra): array
    {
        $merged = $primary;
        $seen = [];

        foreach ($primary as $container) {
            $identity = $this->containerIdentity($container);

            if ($identity !== null) {
                $seen[$identity] = true;
            }
        }

        foreach ($extra as $container) {
            $identity = $this->containerIdentity($container);

            if ($identity !== null && isset($seen[$identity])) {
                continue;
            }

            if ($identity !== null) {
                $seen[$identity] = true;
            }

            $merged[] = $container;
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerIdentity(array $container): ?string
    {
        foreach (['Id', 'id', 'containerId', 'Name', 'name'] as $key) {
            $value = $container[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return ltrim($value, '/');
            }
        }

        $names = $container['Names'] ?? $container['names'] ?? null;

        if (is_array($names)) {
            $first = $names[0] ?? null;

            if (is_string($first) && $first !== '') {
                return ltrim($first, '/');
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function containerList(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter(
                $payload,
                fn (mixed $item): bool => is_array($item),
            ));
        }

        foreach (['result.data', 'data', 'containers'] as $path) {
            $nested = data_get($payload, $path);

            if (is_array($nested) && array_is_list($nested)) {
                return array_values(array_filter(
                    $nested,
                    fn (mixed $item): bool => is_array($item),
                ));
            }
        }

        return [];
    }

    /**
     * Dokploy often returns JSON `true` for create/update/delete with no body object.
     *
     * @return array<int|string, mixed>
     */
    private function jsonArray(mixed $json): array
    {
        if (is_array($json)) {
            return $json;
        }

        if ($json === true || $json === false || $json === null) {
            return [];
        }

        throw new \RuntimeException('Dokploy returned an unexpected JSON payload.');
    }

    private function http(int $timeout = 15): PendingRequest
    {
        $baseUrl = rtrim((string) config('dokploy.url'), '/').'/api';

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'x-api-key' => (string) config('dokploy.api_key'),
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout($timeout);
    }
}
