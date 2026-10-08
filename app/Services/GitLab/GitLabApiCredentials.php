<?php

// GitLabApiCredentials.php — Base URL and bearer token for GitLab API v4 calls.
//
// exports: GitLabApiCredentials
// used_by: app/Services/GitLab/GitLabRepositoryClient.php
//         app/Services/GitLab/DokployGitLabCredentialsResolver.php
//         app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   Never log token. OAuth tokens from Dokploy MUST use Bearer (not PRIVATE-TOKEN header).
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Value object for Toolkit GitLab HTTP auth.

namespace App\Services\GitLab;

readonly class GitLabApiCredentials
{
    public function __construct(
        public string $baseUrl,
        public string $token,
        public string $source = 'pat',
        public ?string $fingerprintSuffix = null,
    ) {}

    public static function fromRuntimeConfig(): ?self
    {
        $token = config('services.gitlab.token');

        if (! is_string($token) || $token === '') {
            return null;
        }

        $baseUrl = rtrim((string) config('services.gitlab.url', 'https://gitlab.com'), '/');

        return new self($baseUrl, $token, 'pat', 'config');
    }

    public function apiBase(): string
    {
        return rtrim($this->baseUrl, '/').'/api/v4';
    }

    public function cacheFingerprint(): string
    {
        $suffix = $this->fingerprintSuffix ?? $this->source;

        return $this->source.':'.$suffix;
    }
}
