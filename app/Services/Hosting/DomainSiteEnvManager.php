<?php

// DomainSiteEnvManager.php — Load, merge, and deploy Dokploy application env for one domain.
//
// exports: DomainSiteEnvManager | DomainSiteEnvManager::panelState(Domain $domain): array | DomainSiteEnvManager::mergeEntries(Domain $domain, list<array{key: string, value: string|null}> $entries): array | DomainSiteEnvManager::deploy(Domain $domain): void
// used_by: app/Http/Controllers/DomainController.php
// rules:   merge via DokployApplicationEnv::replaceAssignments only — never wipe unrelated keys. Blank value on sensitive key keeps existing assignment. Never log env values.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Domain-scoped env + deploy without Dokploy UI login.

namespace App\Services\Hosting;

use App\Models\Domain;
use App\Services\Dokploy\DokployApplicationEnv;
use App\Services\Dokploy\DokployClient;
use App\Services\GitLab\GitLabProjectReference;
use App\Support\DokployEnvPresentation;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainSiteEnvManager
{
    public function __construct(
        private DokployClient $dokploy,
        private DokployApplicationEnv $envParser,
        private DokployEnvPresentation $presentation,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     variables: list<array{key: string, redacted: bool, value?: string, has_value?: bool}>,
     *     git: array{source_type: string|null, repository: string|null, branch: string|null}|null,
     *     error: string|null
     * }
     */
    public function panelState(Domain $domain): array
    {
        $applicationId = $this->applicationId($domain);

        if ($applicationId === null) {
            return [
                'available' => false,
                'variables' => [],
                'git' => null,
                'error' => null,
            ];
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $env = is_string($application['env'] ?? null) ? $application['env'] : '';

            return [
                'available' => true,
                'variables' => $this->presentation->variablesForPanel($env),
                'git' => $this->gitSummary($application),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            return [
                'available' => true,
                'variables' => [],
                'git' => null,
                'error' => $this->dokploy->errorMessage($exception),
            ];
        }
    }

    /**
     * @param  list<array{key: string, value: string|null}>  $entries
     * @return array{added: list<string>, updated: list<string>}
     */
    public function mergeEntries(Domain $domain, array $entries): array
    {
        $applicationId = $this->applicationId($domain);

        if ($applicationId === null) {
            throw ValidationException::withMessages([
                'site_env' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $current = $this->envParser->assignments($currentEnv);
            $replacements = $this->buildReplacements($entries, $current);

            if ($replacements === []) {
                return ['added' => [], 'updated' => []];
            }

            $merged = $this->envParser->replaceAssignments($currentEnv, $replacements);

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);

            return [
                'added' => $merged['added'],
                'updated' => $merged['updated'],
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'site_env' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    public function deploy(Domain $domain): void
    {
        $applicationId = $this->applicationId($domain);

        if ($applicationId === null) {
            throw ValidationException::withMessages([
                'deploy' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        try {
            $this->dokploy->deploy(['applicationId' => $applicationId]);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'deploy' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * @param  array<string, string>  $current
     * @param  list<array{key: string, value: string|null}>  $entries
     * @return array<string, string>
     */
    private function buildReplacements(array $entries, array $current): array
    {
        $replacements = [];

        foreach ($entries as $entry) {
            $key = trim($entry['key']);
            $value = $entry['value'];
            $valueString = is_string($value) ? $value : '';

            if ($key === '') {
                continue;
            }

            if ($this->presentation->isSensitiveKey($key) && trim($valueString) === '') {
                if (array_key_exists($key, $current)) {
                    continue;
                }

                continue;
            }

            $replacements[$key] = $valueString;
        }

        return $replacements;
    }

    /**
     * @param  array<string, mixed>  $application
     * @return array{source_type: string|null, repository: string|null, branch: string|null}
     */
    private function gitSummary(array $application): array
    {
        $sourceType = $application['sourceType'] ?? null;
        $reference = GitLabProjectReference::fromDokployApplication($application);

        return [
            'source_type' => is_string($sourceType) && $sourceType !== '' ? $sourceType : null,
            'repository' => $reference?->projectPath,
            'branch' => $reference?->ref,
        ];
    }

    private function applicationId(Domain $domain): ?string
    {
        $domain->loadMissing('dokployApplication');
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            return null;
        }

        return $applicationId;
    }
}
