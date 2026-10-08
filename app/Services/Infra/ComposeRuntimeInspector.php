<?php

// ComposeRuntimeInspector.php — ComposeRuntimeInspector module.
//
// exports: ComposeRuntimeInspector | ComposeRuntimeInspector::inspect( Infrastructure $infrastructure, int $tail = 300, ?array $services = null, bool $all = false, ): array
// used_by: app/Console/Commands/DokployInspectCommand.php
//         app/Http/Controllers/InfrastructureController.php
//         tests/Unit/ComposeRuntimeInspectorTest.php
// rules:   GRANT_FAIL in mysql-grants logs is the outcome even if an earlier line says GRANT_OK.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_mysql_grants | GRANT_FAIL after GRANT_OK is a failed grants job
// message:

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class ComposeRuntimeInspector
{
    /**
     * @var list<string>
     */
    private const DEFAULT_LOG_SERVICES = ['mysql-grants', 'mariadb', 'phpmyadmin'];

    public function __construct(
        private DokployClient $dokploy,
        private ComposeDeployStatus $composeDeployStatus,
    ) {}

    /**
     * @param  list<string>|null  $services
     * @return array{
     *     slug: string,
     *     compose_id: string,
     *     status: string,
     *     last_error: string|null,
     *     compose_status: string|null,
     *     app_name: string|null,
     *     last_deployment: array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null,
     *     findings: list<array{code: string, severity: string, service: string|null, message: string}>,
     *     containers: list<array{id: string|null, name: string, service: string|null, state: string, status: string}>,
     *     logs: list<array{service: string|null, name: string, id: string|null, state: string, status: string, body: string, error: string|null}>
     * }
     */
    public function inspect(
        Infrastructure $infrastructure,
        int $tail = 300,
        ?array $services = null,
        bool $all = false,
    ): array {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Questa infrastruttura non ha un compose Dokploy.');
        }

        $snapshot = $this->composeDeployStatus->sync($infrastructure);
        $wanted = $all ? null : $this->normalizedServices($services);
        $logs = $this->collectLogs($composeId, $snapshot['containers'], $tail, $wanted);
        $findings = $this->findings($snapshot, $logs, $wanted);

        return [
            'slug' => $infrastructure->slug,
            'compose_id' => $composeId,
            'status' => $snapshot['status'],
            'last_error' => $snapshot['last_error'],
            'compose_status' => $snapshot['compose_status'],
            'app_name' => $snapshot['app_name'],
            'last_deployment' => $snapshot['last_deployment'],
            'findings' => $findings,
            'containers' => $snapshot['containers'],
            'logs' => $logs,
        ];
    }

    /**
     * @param  list<string>|null  $services
     * @return list<string>
     */
    private function normalizedServices(?array $services): array
    {
        $wanted = array_values(array_filter(
            $services ?? self::DEFAULT_LOG_SERVICES,
            fn (string $service): bool => $service !== '',
        ));

        return $wanted === [] ? self::DEFAULT_LOG_SERVICES : $wanted;
    }

    /**
     * @param  list<array{id: string|null, name: string, service: string|null, state: string, status: string}>  $containers
     * @param  list<string>|null  $wanted
     * @return list<array{service: string|null, name: string, id: string|null, state: string, status: string, body: string, error: string|null}>
     */
    private function collectLogs(string $composeId, array $containers, int $tail, ?array $wanted): array
    {
        $logs = [];

        foreach ($containers as $container) {
            $service = $container['service'];

            if (is_array($wanted) && ($service === null || ! in_array($service, $wanted, true))) {
                continue;
            }

            $containerId = $container['id'];

            if (! is_string($containerId) || $containerId === '') {
                $logs[] = [
                    'service' => $service,
                    'name' => $container['name'],
                    'id' => null,
                    'state' => $container['state'],
                    'status' => $container['status'],
                    'body' => '',
                    'error' => 'Dokploy non ha restituito un containerId valido.',
                ];

                continue;
            }

            try {
                $body = $this->dokploy->readComposeLogs($composeId, $containerId, $tail);
                $error = null;
            } catch (Throwable $exception) {
                $body = '';
                $error = $this->dokploy->errorMessage($exception);
            }

            $logs[] = [
                'service' => $service,
                'name' => $container['name'],
                'id' => $containerId,
                'state' => $container['state'],
                'status' => $container['status'],
                'body' => $body,
                'error' => $error,
            ];
        }

        return $logs;
    }

    /**
     * @param  array{
     *     status: string,
     *     last_error: string|null,
     *     last_deployment: array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null,
     *     containers: list<array{id: string|null, name: string, service: string|null, state: string, status: string}>
     * }  $snapshot
     * @param  list<array{service: string|null, name: string, id: string|null, state: string, status: string, body: string, error: string|null}>  $logs
     * @param  list<string>|null  $wanted
     * @return list<array{code: string, severity: string, service: string|null, message: string}>
     */
    private function findings(array $snapshot, array $logs, ?array $wanted): array
    {
        $findings = [];
        $logByService = Collection::make($logs)->keyBy(fn (array $row): string => (string) ($row['service'] ?? $row['name']));
        $include = fn (string $service): bool => $wanted === null || in_array($service, $wanted, true);

        $deploymentError = $snapshot['last_deployment']['error_message'] ?? null;

        if (is_string($deploymentError) && $deploymentError !== '') {
            $findings[] = [
                'code' => 'deploy_error',
                'severity' => 'error',
                'service' => null,
                'message' => $deploymentError,
            ];
        }

        if ($include('mariadb')) {
            $mariadb = $this->containerFor($snapshot['containers'], 'mariadb');
            $mariadbLogs = $logByService->get('mariadb');

            if ($mariadb === null) {
                $findings[] = [
                    'code' => 'mariadb_down',
                    'severity' => 'error',
                    'service' => 'mariadb',
                    'message' => 'Container MariaDB assente su Dokploy.',
                ];
            } elseif (! in_array($mariadb['state'], ['running', 'healthy'], true)) {
                $findings[] = [
                    'code' => 'mariadb_down',
                    'severity' => 'error',
                    'service' => 'mariadb',
                    'message' => 'MariaDB non è in esecuzione ('.$mariadb['state'].').',
                ];
            } elseif (str_contains(strtolower($mariadb['status']), 'unhealthy')) {
                $findings[] = [
                    'code' => 'mariadb_unhealthy',
                    'severity' => 'error',
                    'service' => 'mariadb',
                    'message' => 'MariaDB è unhealthy: '.$mariadb['status'],
                ];
            }

            if (is_array($mariadbLogs) && $this->looksDenied((string) $mariadbLogs['body'])) {
                $findings[] = [
                    'code' => 'mariadb_auth_denied',
                    'severity' => 'warning',
                    'service' => 'mariadb',
                    'message' => 'Nei log MariaDB c’è Access denied (password o host non allineati).',
                ];
            }
        }

        if ($include('mysql-grants')) {
            $grants = $this->containerFor($snapshot['containers'], 'mysql-grants');
            $grantsLogs = $logByService->get('mysql-grants');

            if ($grants === null) {
                $findings[] = [
                    'code' => 'grants_missing',
                    'severity' => 'warning',
                    'service' => 'mysql-grants',
                    'message' => 'Container mysql-grants assente: il job oneshot potrebbe essere già stato rimosso.',
                ];
            } elseif (is_array($grantsLogs)) {
                $body = (string) $grantsLogs['body'];
                $error = $grantsLogs['error'];

                if (is_string($error) && $error !== '') {
                    $findings[] = [
                        'code' => 'grants_logs_unavailable',
                        'severity' => 'warning',
                        'service' => 'mysql-grants',
                        'message' => 'Impossibile leggere i log grants: '.$error,
                    ];
                } elseif (str_contains($body, 'GRANT_FAIL')) {
                    $findings[] = [
                        'code' => 'grants_root_mismatch',
                        'severity' => 'error',
                        'service' => 'mysql-grants',
                        'message' => 'mysql-grants è uscito con GRANT_FAIL: root non accetta più la password del pannello. Aggiorna stack non riallinea un datadir già divergente.',
                    ];
                } elseif (str_contains($body, 'GRANT_OK')) {
                    $findings[] = [
                        'code' => 'grants_aligned',
                        'severity' => 'ok',
                        'service' => 'mysql-grants',
                        'message' => 'mysql-grants ha allineato gli utenti alle password del pannello.',
                    ];
                } elseif ($this->looksDenied($body)) {
                    $findings[] = [
                        'code' => 'grants_root_mismatch',
                        'severity' => 'error',
                        'service' => 'mysql-grants',
                        'message' => 'mysql-grants non è riuscito ad autenticarsi come root: il datadir MariaDB non usa la password del pannello. Aggiorna stack non riallinea.',
                    ];
                } elseif (str_contains((string) $grants['status'], 'Exited (1)') || str_contains((string) $grants['status'], 'exited (1)')) {
                    $findings[] = [
                        'code' => 'grants_failed',
                        'severity' => 'error',
                        'service' => 'mysql-grants',
                        'message' => 'mysql-grants è uscito con codice 1. '.$grants['status'],
                    ];
                } else {
                    $findings[] = [
                        'code' => 'grants_inconclusive',
                        'severity' => 'warning',
                        'service' => 'mysql-grants',
                        'message' => 'Log grants senza GRANT_OK/GRANT_FAIL: il job è partito prima dei marker, oppure i log sono vuoti.',
                    ];
                }
            }
        }

        if ($include('phpmyadmin')) {
            $pmaLogs = $logByService->get('phpmyadmin');

            if (is_array($pmaLogs) && $this->looksDenied((string) $pmaLogs['body'])) {
                $findings[] = [
                    'code' => 'phpmyadmin_denied',
                    'severity' => 'error',
                    'service' => 'phpmyadmin',
                    'message' => 'phpMyAdmin riceve Access denied da MariaDB (prova utente infra, non root, oppure wipe volume DB).',
                ];
            }
        }

        if ($findings === []) {
            $findings[] = [
                'code' => 'ok',
                'severity' => 'ok',
                'service' => null,
                'message' => 'Nessuna anomalia evidente nei log letti.',
            ];
        }

        return $findings;
    }

    /**
     * @param  list<array{id: string|null, name: string, service: string|null, state: string, status: string}>  $containers
     * @return array{id: string|null, name: string, service: string|null, state: string, status: string}|null
     */
    private function containerFor(array $containers, string $service): ?array
    {
        $match = Collection::make($containers)->first(
            fn (array $container): bool => $container['service'] === $service,
        );

        return is_array($match) ? $match : null;
    }

    private function looksDenied(string $body): bool
    {
        $lower = strtolower($body);

        return str_contains($lower, 'access denied') || str_contains($lower, 'error 1045');
    }
}
