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
// message:

namespace App\Services\Laravel;

use App\Models\Domain;
use App\Services\Dokploy\DokployClient;
use App\Services\Dokploy\DokployDashboardUrl;
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
            $appName = $this->stringValue($application, ['appName', 'name']);
            $container = $this->runningContainer($appName);
            $deployment = $this->latestDeployment($applicationId);

            $status = $this->stringValue($application, ['applicationStatus', 'status']);
            $sourceType = $this->stringValue($application, ['sourceType']);
            $gitConfigured = $this->gitLooksConfigured($application);
            $discovered = $this->commandDiscovery->forDomain($domain, $application);

            $ready = $container !== null;
            $containerMessage = 'Prima configura GitLab e Deploy su Dokploy: il container applicazione non è in esecuzione.';

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
            $appName = $this->stringValue($application, ['appName', 'name']);
            $containerId = $this->runningContainer($appName);

            if ($containerId === null) {
                throw ValidationException::withMessages([
                    'command' => 'Prima configura GitLab e Deploy su Dokploy: il container applicazione non è in esecuzione.',
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
     */
    private function gitLooksConfigured(array $application): bool
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

        return false;
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

    private function runningContainer(?string $appName): ?string
    {
        if (! is_string($appName) || $appName === '') {
            return null;
        }

        foreach ($this->dokploy->applicationContainers($appName) as $container) {
            if (! is_array($container)) {
                continue;
            }

            $state = strtolower((string) ($container['State'] ?? $container['state'] ?? ''));

            if ($state !== 'running' && ! str_starts_with($state, 'running')) {
                continue;
            }

            foreach (['Id', 'id', 'containerId'] as $key) {
                $id = $container[$key] ?? null;

                if (is_string($id) && preg_match('/^[a-zA-Z0-9.\-_]+$/', $id) === 1) {
                    return $id;
                }
            }

            $name = $container['Name'] ?? $container['name'] ?? null;

            if (is_string($name) && $name !== '') {
                $trimmed = ltrim($name, '/');

                if (preg_match('/^[a-zA-Z0-9.\-_]+$/', $trimmed) === 1) {
                    return $trimmed;
                }
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
