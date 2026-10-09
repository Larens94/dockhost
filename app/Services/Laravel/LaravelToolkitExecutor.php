<?php

// LaravelToolkitExecutor.php — Reads Dokploy app status for the Laravel toolkit.
//
// exports: LaravelToolkitExecutor | LaravelToolkitExecutor::overview(Domain $domain): array | LaravelToolkitExecutor::artisan(Domain $domain, string $raw): array | LaravelToolkitExecutor::composer(Domain $domain, string $raw): array | LaravelToolkitExecutor::npm(Domain $domain, string $raw): array
// used_by: app/Http/Controllers/LaravelToolkitController.php
//         tests/Feature/LaravelToolkitTest.php
// rules:   CodeDNA header stays above the namespace. Never close the class inside the header or PHP dies with unexpected token "public".
//          overview() catches Dokploy errors and returns message. Without HTTP exec, artisan/composer/npm validate allowlist then return mode=terminal (copy workflow).
//          command_catalog from GitLab discovery when GITLAB_TOKEN set; else fallback. Never print secrets.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_toolkit_terminal | terminal_workflow + mode=terminal quando exec API assente.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_gitlab_url_default | overview status exposes gitlab_url_default for Toolkit modal.
// agent:   grok-4.7 | cursor | 2026-09-21 | s_20260921_toolkit_parse | Moved the CodeDNA block out of the class so overview() parses
// agent:   grok-4.7 | cursor | 2026-10-09 | s_github_source | GitHub counts as configured; Done deploy is not blocked on a missed container list.
// message:

namespace App\Services\Laravel;

use App\Models\Domain;
use App\Services\Dokploy\DokployClient;
use App\Services\Dokploy\DokployDashboardUrl;
use App\Services\GitLab\GitLabProjectReference;
use App\Services\GitLab\GitLabRepositoryClient;
use App\Services\Panel\PanelGitLabCredentialStore;
use Illuminate\Validation\ValidationException;
use Throwable;

class LaravelToolkitExecutor
{
    public function __construct(
        private DokployClient $dokploy,
        private LaravelToolkitCommandAllowlist $allowlist,
        private LaravelToolkitCommandDiscovery $commandDiscovery,
        private GitLabRepositoryClient $gitLab,
        private PanelGitLabCredentialStore $panelGitLab,
    ) {}

    public function overview(Domain $domain): array
    {
        $domain->loadMissing(['dokployApplication', 'infrastructure']);

        $discovered = $this->commandDiscovery->forDomain($domain);
        $gitlabDefaultUrl = $this->panelGitLab->defaultUrlForForm();

        $base = [
            'app_url' => 'https://'.$domain->fqdn,
            'infra_slug' => $domain->infra_slug,
            'application_status' => null,
            'last_deploy_title' => null,
            'last_deploy_status' => null,
            'container_running' => false,
            'dokploy_application_url' => $domain->dokploy_application_url,
            'build_type' => null,
            'source_type' => null,
            'git_provider' => null,
            'git_repository' => null,
            'git_branch' => null,
            'git_configured' => false,
            'ready' => false,
            'exec_available' => false,
            'terminal_workflow' => false,
            'dokploy_terminal_url' => null,
            'exec_message' => DokployClient::HTTP_EXEC_UNAVAILABLE_MESSAGE,
            'message' => null,
            'command_catalog' => $discovered['command_catalog'],
            'command_catalog_source' => $discovered['command_catalog_source'],
            'command_catalog_message' => $discovered['command_catalog_message'],
            'gitlab_url_default' => $gitlabDefaultUrl,
            'panel_gitlab' => [
                'token_configured' => $this->gitLab->tokenConfigured(),
                'dokploy_gitlab_available' => false,
                'credential_source' => $discovered['gitlab_credential_source'] ?? null,
                'default_url' => $gitlabDefaultUrl,
            ],
        ];

        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            return [
                ...$base,
                'exec_message' => 'Crea prima l’application Dokploy da questa scheda.',
                'message' => 'Crea prima l’application Dokploy da questa scheda.',
            ];
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $containers = $this->inspectContainers($application);
            $deployment = $this->latestDeployment($applicationId);

            $status = $this->stringValue($application, ['applicationStatus', 'status']);
            $sourceType = $this->stringValue($application, ['sourceType']);
            $git = $this->gitSummary($application);
            $gitConfigured = $git['configured'];
            $discovered = $this->commandDiscovery->forDomain($domain, $application);

            $ready = $this->applicationIsReady(
                $containers['id'],
                $containers['saw_containers'],
                $status,
                $deployment['status'],
            );
            $containerMessage = __('panel.toolkit.container_not_running');

            return [
                ...$base,
                'command_catalog' => $discovered['command_catalog'],
                'command_catalog_source' => $discovered['command_catalog_source'],
                'command_catalog_message' => $discovered['command_catalog_message'],
                'panel_gitlab' => [
                    'token_configured' => $this->gitLab->tokenConfigured(),
                    'dokploy_gitlab_available' => $this->dokployGitLabConfigured($application),
                    'credential_source' => $discovered['gitlab_credential_source'] ?? null,
                    'default_url' => $gitlabDefaultUrl,
                ],
                'application_status' => $status,
                'last_deploy_title' => $deployment['title'],
                'last_deploy_status' => $deployment['status'],
                'container_running' => $ready,
                'build_type' => $this->stringValue($application, ['buildType']),
                'source_type' => $sourceType,
                'git_provider' => $git['provider'],
                'git_repository' => $git['repository'],
                'git_branch' => $git['branch'],
                'git_configured' => $gitConfigured,
                'ready' => $ready,
                'exec_available' => $ready && $this->dokploy->httpExecIsAvailable(),
                'terminal_workflow' => $ready && ! $this->dokploy->httpExecIsAvailable(),
                'dokploy_terminal_url' => $ready
                    ? app(DokployDashboardUrl::class)->applicationGeneralTab(
                        $domain->infrastructure?->dokploy_project_id,
                        $domain->infrastructure?->dokploy_environment_id,
                        $applicationId,
                    )
                    : null,
                'exec_message' => $ready
                    ? ($this->dokploy->httpExecIsAvailable()
                        ? null
                        : DokployClient::TERMINAL_WORKFLOW_OVERVIEW_MESSAGE)
                    : $containerMessage,
                'message' => $ready ? null : $containerMessage,
            ];
        } catch (Throwable $exception) {
            return [
                ...$base,
                'exec_message' => $this->dokploy->errorMessage($exception),
                'message' => $this->dokploy->errorMessage($exception),
            ];
        }
    }

    /**
     * @return array{command: string, stdout: string, mode?: string, message?: string}
     */
    public function artisan(Domain $domain, string $raw): array
    {
        $normalized = $this->allowlist->artisan($domain, $raw, $this->dokployApplicationPayload($domain));

        return $this->exec($domain, 'php artisan '.$normalized);
    }

    /**
     * @return array{command: string, stdout: string}
     */
    public function composer(Domain $domain, string $raw): array
    {
        $normalized = $this->allowlist->composer($domain, $raw, $this->dokployApplicationPayload($domain));

        return $this->exec($domain, 'composer '.$normalized);
    }

    /**
     * @return array{command: string, stdout: string}
     */
    public function npm(Domain $domain, string $raw): array
    {
        $normalized = $this->allowlist->npm($domain, $raw, $this->dokployApplicationPayload($domain));

        return $this->exec($domain, 'npm '.$normalized);
    }

    /**
     * @return array{command: string, stdout: string, mode?: string, message?: string}
     */
    private function exec(Domain $domain, string $command): array
    {
        $domain->loadMissing(['dokployApplication', 'infrastructure']);

        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            throw ValidationException::withMessages([
                'command' => 'Crea prima l’application Dokploy da questa scheda.',
            ]);
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $containers = $this->inspectContainers($application);
            $deployment = $this->latestDeployment($applicationId);
            $status = $this->stringValue($application, ['applicationStatus', 'status']);

            if (! $this->applicationIsReady($containers['id'], $containers['saw_containers'], $status, $deployment['status'])) {
                throw ValidationException::withMessages([
                    'command' => __('panel.toolkit.container_not_running'),
                ]);
            }

            if ($this->dokploy->httpExecIsAvailable()) {
                // Future: POST docker/application execute when httpExecIsAvailable() is true.
                throw ValidationException::withMessages([
                    'command' => 'Exec API non implementata.',
                ]);
            }

            return [
                'mode' => 'terminal',
                'command' => $command,
                'stdout' => '',
                'message' => DokployClient::TERMINAL_WORKFLOW_RESULT_MESSAGE,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'command' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dokployApplicationPayload(Domain $domain): ?array
    {
        $domain->loadMissing('dokployApplication');

        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            return null;
        }

        try {
            return $this->dokploy->getApplication($applicationId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $application
     * @return array{configured: bool, provider: string|null, repository: string|null, branch: string|null}
     */
    private function gitSummary(array $application): array
    {
        $reference = GitLabProjectReference::fromDokployApplication($application);
        $provider = $this->stringValue($application, ['sourceType']);
        $configured = $reference !== null || $this->gitFieldPresent($application);

        return [
            'configured' => $configured,
            'provider' => $provider,
            'repository' => $reference?->projectPath,
            'branch' => $reference?->ref,
        ];
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function gitFieldPresent(array $application): bool
    {
        foreach ([
            'customGitUrl',
            'repository',
            'gitlabRepository',
            'gitlabRepositoryURL',
            'githubRepository',
            'bitbucketRepository',
            'giteaRepository',
        ] as $key) {
            $value = $application[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        $source = strtolower((string) ($application['sourceType'] ?? ''));

        return in_array($source, ['github', 'gitlab', 'bitbucket', 'gitea', 'git'], true)
            && $this->stringValue($application, ['owner']) !== null;
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function dokployGitLabConfigured(array $application): bool
    {
        if (($application['sourceType'] ?? '') !== 'gitlab') {
            return false;
        }

        if (($application['hasGitProviderAccess'] ?? true) === false) {
            return false;
        }

        $gitlabId = $application['gitlabId'] ?? data_get($application, 'gitlab.gitlabId');

        return is_string($gitlabId) && $gitlabId !== '';
    }

    /**
     * @return array{title: string|null, status: string|null}
     */
    private function latestDeployment(string $applicationId): array
    {
        $deployments = $this->dokploy->applicationDeployments($applicationId);

        foreach ($deployments as $deployment) {
            if (! is_array($deployment)) {
                continue;
            }

            $title = $deployment['title'] ?? $deployment['titleLog'] ?? null;
            $status = $deployment['status'] ?? null;

            return [
                'title' => is_string($title) && $title !== '' ? $title : null,
                'status' => is_string($status) && $status !== '' ? $status : null,
            ];
        }

        return ['title' => null, 'status' => null];
    }

    /**
     * A running container wins. If Dokploy lists nothing, a Done application and a Done
     * deploy still count: the container list can miss the live container while the deploy succeeded.
     * A listed container that is not running stays blocked.
     */
    private function applicationIsReady(?string $containerId, bool $sawContainers, ?string $applicationStatus, ?string $deployStatus): bool
    {
        if (is_string($containerId) && $containerId !== '') {
            return true;
        }

        if ($sawContainers) {
            return false;
        }

        return $this->statusMeansDeployed($applicationStatus) && $this->statusMeansDeployed($deployStatus);
    }

    private function statusMeansDeployed(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), ['done', 'success', 'successful', 'running'], true);
    }

    /**
     * @param  array<string, mixed>  $application
     * @return array{id: string|null, saw_containers: bool}
     */
    private function inspectContainers(array $application): array
    {
        $names = [];

        foreach (['appName', 'name'] as $key) {
            $value = $this->stringValue($application, [$key]);

            if ($value !== null) {
                $names[$value] = true;
            }
        }

        $sawContainers = false;

        foreach (array_keys($names) as $appName) {
            foreach ($this->dokploy->applicationContainers($appName) as $container) {
                if (! is_array($container)) {
                    continue;
                }

                $sawContainers = true;

                if (! $this->containerIsRunning($container)) {
                    continue;
                }

                $identity = $this->containerIdentity($container);

                if ($identity !== null) {
                    return [
                        'id' => $identity,
                        'saw_containers' => true,
                    ];
                }
            }
        }

        return [
            'id' => null,
            'saw_containers' => $sawContainers,
        ];
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerIsRunning(array $container): bool
    {
        $stateValue = $container['State'] ?? $container['state'] ?? null;
        $state = is_array($stateValue)
            ? strtolower((string) ($stateValue['Status'] ?? $stateValue['status'] ?? ''))
            : strtolower(trim((string) $stateValue));

        if ($state === 'running' || $state === 'healthy' || str_starts_with($state, 'running') || str_starts_with($state, 'up')) {
            return true;
        }

        if ($state === '') {
            $status = strtolower((string) ($container['Status'] ?? $container['status'] ?? ''));

            return str_starts_with($status, 'up') || str_contains($status, 'running');
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerIdentity(array $container): ?string
    {
        foreach (['Id', 'id', 'containerId'] as $key) {
            $id = $container[$key] ?? null;

            if (is_string($id) && preg_match('/^[a-zA-Z0-9.\-_]+$/', $id) === 1) {
                return $id;
            }
        }

        $name = $container['Name'] ?? $container['name'] ?? null;

        if (! is_string($name) || $name === '') {
            $names = $container['Names'] ?? $container['names'] ?? null;
            $name = is_array($names) ? ($names[0] ?? null) : null;
        }

        if (is_string($name) && $name !== '') {
            $trimmed = ltrim($name, '/');

            if (preg_match('/^[a-zA-Z0-9.\-_]+$/', $trimmed) === 1) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
