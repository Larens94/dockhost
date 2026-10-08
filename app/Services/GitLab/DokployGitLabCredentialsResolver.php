<?php

// DokployGitLabCredentialsResolver.php — OAuth access token for GitLab API via Dokploy gitlab.one.
//
// exports: DokployGitLabCredentialsResolver | DokployGitLabCredentialsResolver::fromApplication(array $application): ?GitLabApiCredentials
// used_by: app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   application.one nested gitlab omits tokens; gitlab.one returns accessToken (x-api-key only). Never log tokens. Skip when hasGitProviderAccess is false or gitlabId missing.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Per-app Dokploy GitLab OAuth for Toolkit discovery.

namespace App\Services\GitLab;

use App\Services\Dokploy\DokployClient;
use Throwable;

class DokployGitLabCredentialsResolver
{
    public function __construct(
        private DokployClient $dokploy,
    ) {}

    /**
     * @param  array<string, mixed>  $application
     */
    public function fromApplication(array $application): ?GitLabApiCredentials
    {
        if (($application['sourceType'] ?? '') !== 'gitlab') {
            return null;
        }

        if (($application['hasGitProviderAccess'] ?? true) === false) {
            return null;
        }

        $gitlabId = $application['gitlabId'] ?? data_get($application, 'gitlab.gitlabId');

        if (! is_string($gitlabId) || $gitlabId === '') {
            return null;
        }

        try {
            $integration = $this->dokploy->getGitlabIntegration($gitlabId);
        } catch (Throwable) {
            return null;
        }

        $token = $integration['accessToken'] ?? null;
        $baseUrl = $integration['gitlabUrl'] ?? data_get($application, 'gitlab.gitlabUrl');

        if (! is_string($token) || $token === '' || ! is_string($baseUrl) || $baseUrl === '') {
            return null;
        }

        return new GitLabApiCredentials(
            rtrim($baseUrl, '/'),
            $token,
            'dokploy',
            $gitlabId,
        );
    }
}
