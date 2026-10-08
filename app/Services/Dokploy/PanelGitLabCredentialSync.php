<?php

// PanelGitLabCredentialSync.php — Pushes GITLAB_* from runtime config into panel Dokploy env.
//
// exports: PanelGitLabCredentialSync | PanelGitLabCredentialSync::syncFromConfig(): array
// used_by: app/Console/Commands/SyncPanelGitlabEnvCommand.php
// rules:   application.one omits git OAuth; gitlab.one exposes tokens to x-api-key (Toolkit uses that per-app). This sync still pushes panel PAT into dokhosts env for fallback/CI. Never log values. Skip when token missing or Dokploy not configured.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | CI/entrypoint sync; no Dokploy git provider token reuse.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Toolkit can use gitlab.one OAuth; sync job unchanged for PAT fallback.
// message:

namespace App\Services\Dokploy;

use Throwable;

class PanelGitLabCredentialSync
{
    public function __construct(
        private DokployClient $dokploy,
        private PanelApplicationResolver $panelApplication,
        private DokployApplicationEnv $envEditor,
    ) {}

    /**
     * @return array{
     *     status: 'synced'|'skipped_no_token'|'skipped_no_dokploy'|'skipped_no_application'|'skipped_unchanged'|'failed',
     *     application_id: string|null,
     *     updated_keys: list<string>,
     *     added_keys: list<string>,
     *     message: string,
     * }
     */
    public function syncFromConfig(): array
    {
        $url = rtrim((string) config('services.gitlab.url', 'https://gitlab.com'), '/');
        $token = config('services.gitlab.token');

        if (! is_string($token) || $token === '') {
            return [
                'status' => 'skipped_no_token',
                'application_id' => null,
                'updated_keys' => [],
                'added_keys' => [],
                'message' => 'GITLAB_TOKEN non impostato nel processo corrente — nessuna scrittura su Dokploy.',
            ];
        }

        if (! filled(config('dokploy.url')) || ! filled(config('dokploy.api_key'))) {
            return [
                'status' => 'skipped_no_dokploy',
                'application_id' => null,
                'updated_keys' => [],
                'added_keys' => [],
                'message' => 'DOKPLOY_URL o DOKPLOY_API_KEY mancanti — impossibile sincronizzare env pannello.',
            ];
        }

        $applicationId = $this->panelApplication->resolveApplicationId();

        if ($applicationId === null) {
            return [
                'status' => 'skipped_no_application',
                'application_id' => null,
                'updated_keys' => [],
                'added_keys' => [],
                'message' => 'Application Dokploy del pannello non trovata (configura DOKPLOY_SELF_APPLICATION_ID).',
            ];
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';

            $merged = $this->envEditor->replaceAssignments($currentEnv, [
                'GITLAB_URL' => $url,
                'GITLAB_TOKEN' => $token,
            ]);

            if ($merged['added'] === [] && $merged['updated'] === []) {
                return [
                    'status' => 'skipped_unchanged',
                    'application_id' => $applicationId,
                    'updated_keys' => [],
                    'added_keys' => [],
                    'message' => 'GITLAB_URL e GITLAB_TOKEN già allineati su Dokploy.',
                ];
            }

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);

            return [
                'status' => 'synced',
                'application_id' => $applicationId,
                'updated_keys' => $merged['updated'],
                'added_keys' => $merged['added'],
                'message' => 'Env GitLab pannello aggiornato su Dokploy (ridistribuisci dokhosts se il container non ha ancora le variabili).',
            ];
        } catch (Throwable $exception) {
            return [
                'status' => 'failed',
                'application_id' => $applicationId,
                'updated_keys' => [],
                'added_keys' => [],
                'message' => $this->dokploy->errorMessage($exception),
            ];
        }
    }
}
