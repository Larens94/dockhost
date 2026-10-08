<?php


// ComposeDeployStatus.php — ComposeDeployStatus module.
//
// exports: ComposeDeployStatus | ComposeDeployStatus::sync(Infrastructure $infrastructure): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ComposeDeployStatus
{
    /**
     * @var list<string>
     */
    private const HELPER_SERVICES = ['mysql-grants', 'sftp-users-init', 'sftp-sync'];

    public function __construct(private DokployClient $dokploy) {}

    /**
     * @return array{
     *     status: string,
     *     last_error: string|null,
     *     compose_status: string|null,
     *     app_name: string|null,
     *     last_deployment: array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null,
     *     containers: list<array{id: string|null, name: string, service: string|null, state: string, status: string}>
     * }
     */
    public function sync(Infrastructure $infrastructure): array
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Questa infrastruttura non ha un compose Dokploy.');
        }

        try {
            $snapshot = $this->read($infrastructure, $composeId);
        } catch (Throwable $exception) {
            $message = $this->dokploy->errorMessage($exception);
            $infrastructure->update([
                'last_error' => $message,
            ]);

            return [
                'status' => (string) $infrastructure->refresh()->status,
                'last_error' => $message,
                'compose_status' => null,
                'app_name' => null,
                'last_deployment' => null,
                'containers' => [],
            ];
        }

        $infrastructure->update([
            'status' => $snapshot['status'],
            'last_error' => $snapshot['last_error'],
        ]);

        return $snapshot;
    }

    /**
     * @return array{
     *     status: string,
     *     last_error: string|null,
     *     compose_status: string|null,
     *     app_name: string|null,
     *     last_deployment: array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null,
     *     containers: list<array{id: string|null, name: string, service: string|null, state: string, status: string}>
     * }
     */
    private function read(Infrastructure $infrastructure, string $composeId): array
    {
        $compose = $this->unwrapCompose($this->dokploy->getCompose($composeId));
        $appName = $this->stringValue($compose, ['appName', 'name']) ?? $infrastructure->slug;
        $composeStatus = $this->normalizedState($this->stringValue($compose, ['composeStatus', 'status']));
        $lastDeployment = $this->lastDeployment($compose, $composeId);
        $containers = $this->containersFor($appName, $infrastructure->slug);
        $expected = $this->expectedServices($infrastructure);
        $serviceStates = $this->statesForServices($expected, $containers);
        $deploymentState = $this->normalizedState($lastDeployment['status'] ?? null);

        $status = $this->resolveStatus($composeStatus, $deploymentState, $serviceStates, $expected);
        $lastError = $this->resolveError($status, $composeStatus, $lastDeployment, $serviceStates, $expected);

        return [
            'status' => $status,
            'last_error' => $lastError,
            'compose_status' => $composeStatus !== '' ? $composeStatus : null,
            'app_name' => $appName,
            'last_deployment' => $lastDeployment,
            'containers' => $containers,
        ];
    }

    /**
     * @param  array<string, mixed>  $compose
     * @return array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null
     */
    private function lastDeployment(array $compose, string $composeId): ?array
    {
        $rows = $this->maps($compose['deployments'] ?? null);

        if ($rows === []) {
            $rows = $this->maps($this->dokploy->composeDeployments($composeId));
        }

        $sorted = Collection::make($rows)
            ->sortByDesc(function (array $row): string {
                return (string) ($row['createdAt'] ?? $row['startedAt'] ?? $row['finishedAt'] ?? '');
            })
            ->values();

        $latest = $sorted->first();

        if (! is_array($latest)) {
            return null;
        }

        $error = $this->stringValue($latest, ['errorMessage', 'error', 'message']);

        return [
            'status' => $this->normalizedState($this->stringValue($latest, ['status'])) ?: null,
            'title' => $this->stringValue($latest, ['title', 'description']),
            'created_at' => $this->stringValue($latest, ['createdAt', 'startedAt']),
            'error_message' => $error,
        ];
    }

    /**
     * @return list<array{id: string|null, name: string, service: string|null, state: string, status: string}>
     */
    private function containersFor(string $appName, string $slug): array
    {
        try {
            $payload = $this->dokploy->containersByAppName($appName);
        } catch (Throwable) {
            if ($appName === $slug) {
                return [];
            }

            try {
                $payload = $this->dokploy->containersByAppName($slug);
            } catch (Throwable) {
                return [];
            }
        }

        return array_values(array_map(function (array $item): array {
            $name = ltrim((string) ($item['Name'] ?? $item['name'] ?? $this->firstName($item['Names'] ?? null) ?? ''), '/');
            $rawId = ltrim((string) ($item['Id'] ?? $item['ID'] ?? $item['id'] ?? $item['containerId'] ?? ''), '/');
            $stateValue = $item['State'] ?? $item['state'] ?? null;
            $state = is_array($stateValue)
                ? (string) ($stateValue['Status'] ?? $stateValue['status'] ?? '')
                : (string) $stateValue;
            $status = (string) ($item['Status'] ?? $item['status'] ?? '');

            return [
                'id' => $this->usableContainerId($rawId, $name),
                'name' => $name !== '' ? $name : '(senza nome)',
                'service' => $this->serviceFromName($name),
                'state' => $this->normalizedState($state !== '' ? $state : $this->stateFromStatus($status)),
                'status' => $status,
            ];
        }, $this->maps($payload)));
    }

    /**
     * @param  list<string>  $expected
     * @param  list<array{id: string|null, name: string, service: string|null, state: string, status: string}>  $containers
     * @return array<string, string>
     */
    private function statesForServices(array $expected, array $containers): array
    {
        $states = [];

        foreach ($expected as $service) {
            $match = Collection::make($containers)->first(
                fn (array $container): bool => $container['service'] === $service
                    || str_contains(strtolower($container['name']), '-'.$service)
                    || str_contains(strtolower($container['name']), $service.'-'),
            );

            $states[$service] = is_array($match) ? $match['state'] : 'missing';
        }

        return $states;
    }

    /**
     * @return list<string>
     */
    private function expectedServices(Infrastructure $infrastructure): array
    {
        $enabled = $infrastructure->enabled_services ?? app(ComposeTemplate::class)->defaultEnabledServices();

        return array_values(array_filter(
            $enabled,
            fn (string $service): bool => ! in_array($service, self::HELPER_SERVICES, true),
        ));
    }

    /**
     * @param  array<string, string>  $serviceStates
     * @param  list<string>  $expected
     */
    private function resolveStatus(string $composeStatus, string $deploymentState, array $serviceStates, array $expected): string
    {
        if ($composeStatus === 'error' || $deploymentState === 'error') {
            return 'failed';
        }

        $hasRestarting = Collection::make($serviceStates)->contains(
            fn (string $state): bool => $this->isRestarting($state),
        );

        if ($hasRestarting) {
            return 'degraded';
        }

        $mariadb = $serviceStates['mariadb'] ?? 'missing';
        $optionalDown = Collection::make($expected)
            ->reject(fn (string $service): bool => $service === 'mariadb')
            ->contains(fn (string $service): bool => ! $this->isRunning($serviceStates[$service] ?? 'missing'));

        if ($this->isRunning($mariadb) && ! $optionalDown) {
            return 'deployed';
        }

        if (in_array($composeStatus, ['running'], true) || in_array($deploymentState, ['running', 'pending'], true)) {
            return 'deploying';
        }

        if ($serviceStates === [] || Collection::make($serviceStates)->every(fn (string $state): bool => $state === 'missing')) {
            if (in_array($composeStatus, ['done', 'idle'], true) && in_array($deploymentState, ['done', ''], true)) {
                return 'deployed';
            }

            return 'deploying';
        }

        return 'failed';
    }

    /**
     * @param  array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null  $lastDeployment
     * @param  array<string, string>  $serviceStates
     * @param  list<string>  $expected
     */
    private function resolveError(
        string $status,
        string $composeStatus,
        ?array $lastDeployment,
        array $serviceStates,
        array $expected,
    ): ?string {
        $deploymentError = is_string($lastDeployment['error_message'] ?? null)
            ? $lastDeployment['error_message']
            : null;

        if ($status === 'failed') {
            if (is_string($deploymentError) && $deploymentError !== '') {
                return $deploymentError;
            }

            if ($composeStatus === 'error') {
                return 'Il compose Dokploy è in errore.';
            }

            if (($serviceStates['mariadb'] ?? 'missing') !== 'running' && ! $this->isRunning($serviceStates['mariadb'] ?? 'missing')) {
                return 'MariaDB non è in esecuzione su Dokploy.';
            }

            $down = Collection::make($expected)
                ->first(fn (string $service): bool => ! $this->isRunning($serviceStates[$service] ?? 'missing'));

            if (is_string($down)) {
                return 'Il servizio '.$down.' non è in esecuzione su Dokploy.';
            }

            return 'Il deploy Dokploy non è riuscito.';
        }

        if ($status === 'degraded') {
            return 'Alcuni servizi sono in riavvio su Dokploy.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function unwrapCompose(array $payload): array
    {
        foreach (['result.data', 'data', 'compose'] as $path) {
            $nested = data_get($payload, $path);

            if (is_array($nested) && $nested !== [] && array_is_list($nested) === false) {
                return $nested;
            }
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function maps(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return array_values(array_filter($payload, is_array(...)));
        }

        foreach (['result.data', 'data', 'containers', 'items'] as $path) {
            $nested = data_get($payload, $path);

            if (is_array($nested) && array_is_list($nested)) {
                return array_values(array_filter($nested, is_array(...)));
            }
        }

        return [$payload];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function usableContainerId(string $id, string $name): ?string
    {
        foreach ([$id, $name] as $candidate) {
            if ($candidate !== '' && preg_match('/^[a-zA-Z0-9.\-_]+$/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    private function firstName(mixed $names): ?string
    {
        if (is_string($names) && $names !== '') {
            return $names;
        }

        if (is_array($names)) {
            $first = $names[0] ?? null;

            return is_string($first) && $first !== '' ? $first : null;
        }

        return null;
    }

    private function serviceFromName(string $name): ?string
    {
        $known = ['phpmyadmin', 'pgadmin', 'mysql-grants', 'mariadb', 'postgres', 'minio', 'redis', 'sftp-users-init', 'sftp-sync', 'sftp'];

        foreach ($known as $service) {
            if (str_contains(strtolower($name), $service)) {
                return $service;
            }
        }

        return null;
    }

    private function stateFromStatus(string $status): string
    {
        $lower = strtolower($status);

        if (str_starts_with($lower, 'up') || str_contains($lower, 'running')) {
            return 'running';
        }

        if (str_contains($lower, 'restart')) {
            return 'restarting';
        }

        if (str_contains($lower, 'exit')) {
            return 'exited';
        }

        return $lower;
    }

    private function normalizedState(?string $state): string
    {
        return strtolower(trim((string) $state));
    }

    private function isRunning(string $state): bool
    {
        return in_array($state, ['running', 'healthy'], true);
    }

    private function isRestarting(string $state): bool
    {
        return in_array($state, ['restarting', 'restart'], true);
    }
}
