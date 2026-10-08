<?php

// GitLabProjectReference.php — Parses Dokploy application payload into GitLab project path and ref.
//
// exports: GitLabProjectReference | GitLabProjectReference::fromDokployApplication(array $application): ?GitLabProjectReference
// used_by: app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   Never log or expose GITLAB_TOKEN. projectPath is namespace/project without .git suffix.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Dokploy gitlabRepository and customGitUrl parsing.
// message:

namespace App\Services\GitLab;

readonly class GitLabProjectReference
{
    public function __construct(
        public string $projectPath,
        public string $ref,
    ) {}

    /**
     * @param  array<string, mixed>  $application
     */
    public static function fromDokployApplication(array $application): ?self
    {
        $projectPath = self::resolveProjectPath($application);

        if ($projectPath === null) {
            return null;
        }

        $ref = self::resolveRef($application);

        return new self($projectPath, $ref);
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private static function resolveProjectPath(array $application): ?string
    {
        $repository = $application['gitlabRepository'] ?? null;

        if (is_string($repository) && $repository !== '') {
            $owner = $application['gitlabOwner'] ?? $application['gitlabProjectOwner'] ?? null;

            if (is_string($owner) && $owner !== '' && ! str_contains($repository, '/')) {
                return self::normalizeProjectPath($owner.'/'.$repository);
            }

            if (str_contains($repository, '/')) {
                return self::normalizeProjectPath($repository);
            }
        }

        foreach (['gitlabRepositoryURL', 'customGitUrl', 'repository'] as $key) {
            $url = $application[$key] ?? null;

            if (! is_string($url) || $url === '') {
                continue;
            }

            $fromUrl = self::projectPathFromGitUrl($url);

            if ($fromUrl !== null) {
                return $fromUrl;
            }
        }

        return null;
    }

    private static function projectPathFromGitUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('#^git@[^:]+:(.+?)(?:\.git)?/?$#i', $url, $sshMatches) === 1) {
            return self::normalizeProjectPath($sshMatches[1]);
        }

        if (preg_match('#^https?://[^/]+/(.+?)(?:\.git)?/?$#i', $url, $httpsMatches) === 1) {
            return self::normalizeProjectPath($httpsMatches[1]);
        }

        return null;
    }

    private static function normalizeProjectPath(string $path): ?string
    {
        $path = trim($path, "/ \t\n\r\0\x0B");
        $path = preg_replace('#\.git$#i', '', $path) ?? $path;

        if ($path === '' || ! preg_match('#^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$#', $path)) {
            return null;
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private static function resolveRef(array $application): string
    {
        foreach (['gitlabBranch', 'gitBranch', 'branch', 'customGitBranch'] as $key) {
            $value = $application[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'main';
    }
}
