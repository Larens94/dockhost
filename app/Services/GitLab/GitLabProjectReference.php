<?php

// GitLabProjectReference.php — Parses Dokploy application payload into GitLab project path and ref.
//
// exports: GitLabProjectReference | GitLabProjectReference::fromDokployApplication(array $application): ?GitLabProjectReference
// used_by: app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   Never log or expose GITLAB_TOKEN. projectPath is namespace/project without .git suffix.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Dokploy gitlabRepository and customGitUrl parsing.
// agent:   grok-4.7 | cursor | 2026-10-09 | s_github_source | owner/repository for GitHub, GitLab, Bitbucket, and Gitea.
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
        foreach ([
            ['gitlabRepository', ['gitlabOwner', 'gitlabProjectOwner', 'owner']],
            ['githubRepository', ['githubOwner', 'owner']],
            ['bitbucketRepository', ['bitbucketOwner', 'owner']],
            ['bitbucketRepositorySlug', ['bitbucketOwner', 'owner']],
            ['giteaRepository', ['giteaOwner', 'owner']],
            ['repository', ['owner', 'githubOwner', 'gitlabOwner', 'bitbucketOwner', 'giteaOwner']],
        ] as [$repositoryKey, $ownerKeys]) {
            $path = self::ownerRepositoryPath($application, $repositoryKey, $ownerKeys);

            if ($path !== null) {
                return $path;
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

    /**
     * @param  array<string, mixed>  $application
     * @param  list<string>  $ownerKeys
     */
    private static function ownerRepositoryPath(array $application, string $repositoryKey, array $ownerKeys): ?string
    {
        $repository = $application[$repositoryKey] ?? null;

        if (! is_string($repository) || trim($repository) === '') {
            return null;
        }

        $repository = trim($repository);

        if (str_contains($repository, '://') || str_starts_with($repository, 'git@')) {
            return self::projectPathFromGitUrl($repository);
        }

        if (str_contains($repository, '/')) {
            return self::normalizeProjectPath($repository);
        }

        foreach ($ownerKeys as $ownerKey) {
            $owner = $application[$ownerKey] ?? null;

            if (is_string($owner) && trim($owner) !== '') {
                return self::normalizeProjectPath(trim($owner).'/'.$repository);
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
        foreach (['gitlabBranch', 'githubBranch', 'bitbucketBranch', 'giteaBranch', 'gitBranch', 'branch', 'customGitBranch'] as $key) {
            $value = $application[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return 'main';
    }
}
