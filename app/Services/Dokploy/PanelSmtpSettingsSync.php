<?php

// PanelSmtpSettingsSync.php — Pushes MAIL_* from runtime config into panel Dokploy env.
//
// exports: PanelSmtpSettingsSync | PanelSmtpSettingsSync::syncFromConfig(): array
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   Update ONLY MAIL_MAILER and MAIL_* keys via env editor. Never log env values. Skip when password missing or Dokploy not configured.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | Dokploy self-app MAIL_* sync like GitLab PAT.

namespace App\Services\Dokploy;

use App\Services\Panel\PanelSmtpSettingsStore;
use Throwable;

class PanelSmtpSettingsSync
{
    public function __construct(
        private DokployClient $dokploy,
        private PanelApplicationResolver $panelApplication,
        private DokployApplicationEnv $envEditor,
        private PanelSmtpSettingsStore $store,
    ) {}

    /**
     * @return array{
     *     status: 'synced'|'skipped_no_password'|'skipped_no_dokploy'|'skipped_no_application'|'skipped_unchanged'|'failed',
     *     application_id: string|null,
     *     updated_keys: list<string>,
     *     added_keys: list<string>,
     *     message: string,
     * }
     */
    public function syncFromConfig(): array
    {
        $assignments = $this->store->mailEnvAssignmentsFromConfig();

        if ($assignments === []) {
            return [
                'status' => 'skipped_no_password',
                'application_id' => null,
                'updated_keys' => [],
                'added_keys' => [],
                'message' => 'MAIL_PASSWORD non impostata nel processo corrente — nessuna scrittura su Dokploy.',
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

        $envAssignments = $assignments;
        if ($envAssignments['MAIL_ENCRYPTION'] === 'null') {
            $envAssignments['MAIL_ENCRYPTION'] = '';
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';

            $merged = $this->envEditor->replaceAssignments($currentEnv, $envAssignments);

            if ($merged['added'] === [] && $merged['updated'] === []) {
                return [
                    'status' => 'skipped_unchanged',
                    'application_id' => $applicationId,
                    'updated_keys' => [],
                    'added_keys' => [],
                    'message' => 'Variabili MAIL_* già allineate su Dokploy.',
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
                'message' => 'Env SMTP pannello aggiornato su Dokploy.',
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
