<?php

// PanelApplicationResolver.php — Resolves Dokploy application id for the dokhosts panel.
//
// exports: PanelApplicationResolver | PanelApplicationResolver::resolveApplicationId(): ?string
// used_by: app/Services/Dokploy/PanelGitLabCredentialSync.php
//         app/Services/Dokploy/PanelSmtpSettingsSync.php
//         app/Http/Controllers/PanelSmtpSettingsController.php
//         app/Console/Commands/SyncPanelGitlabEnvCommand.php
// rules:   Prefer config dokploy.self_application_id; else scan project.all for name dokhosts. Never log API keys.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | Self-application id from config or discovery.
// message:

namespace App\Services\Dokploy;

use Throwable;

class PanelApplicationResolver
{
    public function __construct(
        private DokployClient $dokploy,
    ) {}

    public function resolveApplicationId(): ?string
    {
        $configured = config('dokploy.self_application_id');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $expectedName = strtolower((string) config('dokploy.panel_application_name', 'dokhosts'));

        try {
            foreach ($this->dokploy->allProjects() as $project) {
                if (! is_array($project)) {
                    continue;
                }

                $environments = $project['environments'] ?? [];

                if (! is_array($environments)) {
                    continue;
                }

                foreach ($environments as $environment) {
                    if (! is_array($environment)) {
                        continue;
                    }

                    foreach ($environment['applications'] ?? [] as $application) {
                        if (! is_array($application)) {
                            continue;
                        }

                        $name = $application['name'] ?? $application['appName'] ?? null;

                        if (! is_string($name) || $name === '') {
                            continue;
                        }

                        if (strtolower($name) !== $expectedName && ! str_contains(strtolower($name), $expectedName)) {
                            continue;
                        }

                        $applicationId = $application['applicationId'] ?? $application['id'] ?? null;

                        if (is_string($applicationId) && $applicationId !== '') {
                            return $applicationId;
                        }
                    }
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
