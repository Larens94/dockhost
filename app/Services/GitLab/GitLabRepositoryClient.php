<?php

// GitLabRepositoryClient.php — Read-only GitLab repository file API for Toolkit discovery.
//
// exports: GitLabRepositoryClient | GitLabRepositoryClient::fetchRawFile(string $projectPath, string $ref, string $filePath, ?GitLabApiCredentials $credentials = null): ?string | GitLabRepositoryClient::listTreePaths(string $projectPath, string $ref, string $path, bool $recursive = true, ?GitLabApiCredentials $credentials = null): array | GitLabRepositoryClient::tokenConfigured(): bool
// used_by: app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   Prefer Dokploy gitlab.one OAuth per application; panel/env PAT is fallback. Never log file bodies or tokens.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | GitLab API v4 raw file and tree listing.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Optional credentials; Bearer for OAuth tokens.

namespace App\Services\GitLab;

use Illuminate\Support\Facades\Http;

class GitLabRepositoryClient
{
    /**
     * @return list<string> Repository paths (blobs only)
     */
    public function listTreePaths(
        string $projectPath,
        string $ref,
        string $path,
        bool $recursive = true,
        ?GitLabApiCredentials $credentials = null,
    ): array {
        $credentials = $this->resolveCredentials($credentials);

        if ($credentials === null) {
            return [];
        }

        $encodedProject = rawurlencode($projectPath);
        $response = Http::withToken($credentials->token)
            ->acceptJson()
            ->get($credentials->apiBase().'/projects/'.$encodedProject.'/repository/tree', [
                'ref' => $ref,
                'path' => $path,
                'recursive' => $recursive ? 'true' : 'false',
                'per_page' => 100,
            ]);

        if (! $response->successful()) {
            return [];
        }

        $paths = [];
        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        foreach ($payload as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['type'] ?? '') !== 'blob') {
                continue;
            }

            $entryPath = $entry['path'] ?? null;

            if (is_string($entryPath) && $entryPath !== '') {
                $paths[] = $entryPath;
            }
        }

        return $paths;
    }

    public function fetchRawFile(
        string $projectPath,
        string $ref,
        string $filePath,
        ?GitLabApiCredentials $credentials = null,
    ): ?string {
        $credentials = $this->resolveCredentials($credentials);

        if ($credentials === null) {
            return null;
        }

        $encodedProject = rawurlencode($projectPath);
        $encodedFile = rawurlencode($filePath);

        $response = Http::withToken($credentials->token)
            ->accept('text/plain')
            ->get($credentials->apiBase().'/projects/'.$encodedProject.'/repository/files/'.$encodedFile.'/raw', [
                'ref' => $ref,
            ]);

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        return $body !== '' ? $body : null;
    }

    public function tokenConfigured(): bool
    {
        return GitLabApiCredentials::fromRuntimeConfig() !== null;
    }

    private function resolveCredentials(?GitLabApiCredentials $credentials): ?GitLabApiCredentials
    {
        if ($credentials !== null && $credentials->token !== '') {
            return $credentials;
        }

        return GitLabApiCredentials::fromRuntimeConfig();
    }
}
