<?php

// DokployDashboardUrl.php — DokployDashboardUrl module.
//
// exports: DokployDashboardUrl | DokployDashboardUrl::baseUrl(): string | DokployDashboardUrl::projectEnvironment(?string $projectId, ?string $environmentId) | DokployDashboardUrl::compose(?string $projectId, ?string $environmentId, ?string $composeId) | DokployDashboardUrl::application(?string $projectId, ?string $environmentId, ?string $applicationId) | DokployDashboardUrl::applicationGeneralTab(?string $projectId, ?string $environmentId, ?string $applicationId)
// used_by: tests/Unit/DokployDashboardUrlTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Services\Dokploy;

class DokployDashboardUrl
{
    public function baseUrl(): string
    {
        return rtrim((string) config('dokploy.url'), '/');
    }

    public function projectEnvironment(?string $projectId, ?string $environmentId): ?string
    {
        if ($this->baseUrl() === '' || ! filled($projectId) || ! filled($environmentId)) {
            return null;
        }

        return sprintf(
            '%s/dashboard/project/%s/environment/%s',
            $this->baseUrl(),
            $projectId,
            $environmentId,
        );
    }

    public function compose(?string $projectId, ?string $environmentId, ?string $composeId): ?string
    {
        $environmentUrl = $this->projectEnvironment($projectId, $environmentId);

        if ($environmentUrl === null || ! filled($composeId)) {
            return null;
        }

        return $environmentUrl.'/services/compose/'.$composeId;
    }

    public function application(?string $projectId, ?string $environmentId, ?string $applicationId): ?string
    {
        $environmentUrl = $this->projectEnvironment($projectId, $environmentId);

        if ($environmentUrl === null || ! filled($applicationId)) {
            return null;
        }

        return $environmentUrl.'/services/application/'.$applicationId;
    }

    /**
     * General tab hosts Dokploy «Open Terminal» (WebSocket /docker-container-terminal).
     */
    public function applicationGeneralTab(?string $projectId, ?string $environmentId, ?string $applicationId): ?string
    {
        $url = $this->application($projectId, $environmentId, $applicationId);

        if ($url === null) {
            return null;
        }

        return $url.'?tab=general';
    }
}
