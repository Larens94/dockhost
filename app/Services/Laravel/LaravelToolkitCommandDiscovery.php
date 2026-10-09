<?php

// LaravelToolkitCommandDiscovery.php — Builds Toolkit catalogs from GitLab repo source with cache.
//
// exports: LaravelToolkitCommandDiscovery | LaravelToolkitCommandDiscovery::forDomain(Domain $domain, ?array $dokployApplication): array
// used_by: app/Services/Laravel/LaravelToolkitExecutor.php
//         app/Services/Laravel/LaravelToolkitCommandAllowlist.php
// rules:   Cache TTL 900s per domain+project+ref+credential source. Prefer Dokploy gitlab.one OAuth per application; panel/env PAT is fallback. application.one omits tokens; gitlab.one requires x-api-key. Never cache or log secrets.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | GitLab tree fetch for Console commands and package manifests.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Composer script merge + git source key; safe script filter.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | Fallback message points to CI sync; no Dokploy OAuth token reuse.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_dokploy_gitlab_link | Dokploy gitlab.one OAuth for Toolkit when PAT absent.
// agent:   grok-4.7 | cursor | 2026-10-09 | s_github_source | GitHub/Git sources stay configured; GitLab API is not the gate.

namespace App\Services\Laravel;

use App\Models\Domain;
use App\Services\GitLab\DokployGitLabCredentialsResolver;
use App\Services\GitLab\GitLabApiCredentials;
use App\Services\GitLab\GitLabProjectReference;
use App\Services\GitLab\GitLabRepositoryClient;
use App\Services\Panel\PanelGitLabCredentialStore;
use Illuminate\Support\Facades\Cache;

class LaravelToolkitCommandDiscovery
{
    private const CACHE_TTL_SECONDS = 900;

    public function __construct(
        private GitLabRepositoryClient $gitLab,
        private DokployGitLabCredentialsResolver $dokployGitLab,
        private LaravelArtisanSignatureParser $parser,
        private LaravelToolkitCommandCatalog $catalog,
        private PanelGitLabCredentialStore $panelGitLab,
    ) {}

    /**
     * @param  array<string, mixed>|null  $dokployApplication
     * @return array{
     *     command_catalog: array{artisan: list<array>, composer: list<array>, npm: list<array>},
     *     command_catalog_source: 'git'|'fallback',
     *     command_catalog_message: string|null,
     *     artisan_names: list<string>,
     *     composer_names: list<string>,
     *     gitlab_credential_source: 'dokploy'|'pat'|null,
     *     npm_run_scripts: list<string>,
     * }
     */
    public function forDomain(Domain $domain, ?array $dokployApplication = null): array
    {
        $reference = is_array($dokployApplication)
            ? GitLabProjectReference::fromDokployApplication($dokployApplication)
            : null;

        if ($reference !== null && is_array($dokployApplication) && ! $this->readsCatalogFromGitLab($dokployApplication)) {
            return $this->fallbackPayload(__('panel.toolkit.catalog_source_configured', [
                'provider' => $this->providerLabel($dokployApplication),
                'repository' => $reference->projectPath,
                'branch' => $reference->ref,
            ]));
        }

        $credentials = $this->resolveCredentials($dokployApplication);

        if ($reference === null || $credentials === null) {
            return $this->fallbackPayload(
                $reference === null
                    ? __('panel.toolkit.catalog_git_missing')
                    : __('panel.toolkit.catalog_gitlab_token'),
            );
        }

        $cacheKey = sprintf(
            'laravel_toolkit_catalog:%s:domain:%d:%s:%s:%s',
            $this->panelGitLab->catalogCacheEpoch(),
            $domain->id,
            sha1($reference->projectPath),
            sha1($reference->ref),
            sha1($credentials->cacheFingerprint()),
        );

        /** @var array<string, mixed> $payload */
        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($reference, $credentials): array {
            return $this->discoverFromGit($reference, $credentials);
        });

        return $payload;
    }

    /**
     * Command discovery calls the GitLab API. GitHub, Bitbucket, Gitea, and generic Git
     * already stored on Dokploy stay configured and keep the default catalog.
     *
     * @param  array<string, mixed>  $application
     */
    private function readsCatalogFromGitLab(array $application): bool
    {
        $source = strtolower(trim((string) ($application['sourceType'] ?? '')));

        if (in_array($source, ['github', 'bitbucket', 'gitea'], true)) {
            return false;
        }

        if ($source === 'git') {
            foreach (['customGitUrl', 'repository', 'gitlabRepositoryURL'] as $key) {
                $url = $application[$key] ?? null;

                if (is_string($url) && (str_contains($url, '://') || str_starts_with($url, 'git@'))) {
                    return $this->urlLooksLikeGitLab($url);
                }
            }
        }

        return true;
    }

    private function urlLooksLikeGitLab(string $url): bool
    {
        if (preg_match('#^git@([^:]+):#', $url, $matches) === 1) {
            $host = strtolower($matches[1]);
        } else {
            $host = parse_url($url, PHP_URL_HOST);
            $host = is_string($host) ? strtolower($host) : '';
        }

        if ($host === '') {
            return false;
        }

        return str_contains($host, 'gitlab') || $host === 'git.silicoreautomation.com';
    }

    /**
     * @param  array<string, mixed>  $application
     */
    private function providerLabel(array $application): string
    {
        return match (strtolower(trim((string) ($application['sourceType'] ?? '')))) {
            'github' => 'GitHub',
            'gitlab' => 'GitLab',
            'bitbucket' => 'Bitbucket',
            'gitea' => 'Gitea',
            'git' => 'Git',
            default => 'Git',
        };
    }

    /**
     * @param  array<string, mixed>|null  $dokployApplication
     */
    private function resolveCredentials(?array $dokployApplication): ?GitLabApiCredentials
    {
        if (is_array($dokployApplication)) {
            $fromDokploy = $this->dokployGitLab->fromApplication($dokployApplication);

            if ($fromDokploy !== null) {
                return $fromDokploy;
            }
        }

        return GitLabApiCredentials::fromRuntimeConfig();
    }

    /**
     * @return array{
     *     command_catalog: array{artisan: list<array>, composer: list<array>, npm: list<array>},
     *     command_catalog_source: 'git'|'fallback',
     *     command_catalog_message: string|null,
     *     artisan_names: list<string>,
     *     composer_names: list<string>,
     *     gitlab_credential_source: 'dokploy'|'pat',
     *     npm_run_scripts: list<string>,
     * }
     */
    private function discoverFromGit(GitLabProjectReference $reference, GitLabApiCredentials $credentials): array
    {
        $discoveredArtisan = $this->discoverArtisanNames($reference, $credentials);
        $composerScripts = $this->discoverComposerScriptNames($reference, $credentials);
        $npmScripts = $this->discoverNpmScriptNames($reference, $credentials);

        if ($discoveredArtisan === [] && $composerScripts === [] && $npmScripts === []) {
            return $this->fallbackPayload(
                'GitLab non ha restituito file analizzabili (permessi, branch o path). Catalogo minimo predefinito.',
            );
        }

        $fallback = $this->catalog->fallbackForUi();
        $deny = $this->catalog->deniedArtisanCommandNames();

        $projectArtisan = array_values(array_filter(
            $discoveredArtisan,
            fn (string $name): bool => ! in_array($name, $deny, true)
                && ! $this->catalog->isArtisanNameDeniedByPattern($name),
        ));

        $fallbackArtisanNames = $this->catalog->artisanCommandNamesFromCategories($fallback['artisan']);
        $artisanNames = $this->uniqueSorted([...$fallbackArtisanNames, ...$projectArtisan]);

        $projectOnly = array_values(array_diff($projectArtisan, $fallbackArtisanNames));

        $artisanCategories = $fallback['artisan'];

        if ($projectOnly !== []) {
            array_unshift($artisanCategories, [
                'id' => 'project-git',
                'label' => 'Comandi dal repository Git',
                'commands' => array_map(
                    fn (string $name): array => ['label' => $name, 'command' => $name],
                    $projectOnly,
                ),
            ]);
        }

        $composerCategories = $this->buildComposerCategories($fallback['composer'], $composerScripts);
        $composerNames = $this->catalog->composerCommandNamesFromCategories($composerCategories);

        $npmCategories = $this->buildNpmCategories($fallback['npm'], $npmScripts);
        $npmRunScripts = $this->catalog->npmRunScriptsFromCategories($npmCategories);

        return [
            'command_catalog' => [
                'artisan' => $artisanCategories,
                'composer' => $composerCategories,
                'npm' => $npmCategories,
            ],
            'command_catalog_source' => 'git',
            'command_catalog_message' => 'Catalogo da Git ('.$reference->projectPath.' @ '.$reference->ref.'). '
                .($credentials->source === 'dokploy'
                    ? 'Credenziali: GitLab collegato su Dokploy (OAuth, solo lettura repo).'
                    : 'Credenziali: token pannello / env.').' Aggiornato ogni 15 minuti.',
            'gitlab_credential_source' => $credentials->source === 'dokploy' ? 'dokploy' : 'pat',
            'artisan_names' => $artisanNames,
            'composer_names' => $composerNames,
            'npm_run_scripts' => $npmRunScripts,
        ];
    }

    /**
     * @return list<string>
     */
    private function discoverArtisanNames(GitLabProjectReference $reference, GitLabApiCredentials $credentials): array
    {
        $names = [];

        $consoleRoutes = $this->gitLab->fetchRawFile($reference->projectPath, $reference->ref, 'routes/console.php', $credentials);

        if (is_string($consoleRoutes)) {
            $names = [...$names, ...$this->parser->fromConsoleRoutesSource($consoleRoutes)];
        }

        foreach ($this->gitLab->listTreePaths($reference->projectPath, $reference->ref, 'app/Console/Commands', true, $credentials) as $path) {
            if (! str_ends_with($path, '.php')) {
                continue;
            }

            $source = $this->gitLab->fetchRawFile($reference->projectPath, $reference->ref, $path, $credentials);

            if (! is_string($source)) {
                continue;
            }

            $names = [...$names, ...$this->parser->fromCommandClassSource($source)];
        }

        return $this->uniqueSorted($names);
    }

    /**
     * @return list<string>
     */
    private function discoverComposerScriptNames(GitLabProjectReference $reference, GitLabApiCredentials $credentials): array
    {
        $raw = $this->gitLab->fetchRawFile($reference->projectPath, $reference->ref, 'composer.json', $credentials);

        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $scripts = $decoded['scripts'] ?? null;

        if (! is_array($scripts)) {
            return [];
        }

        $names = [];

        foreach ($scripts as $key => $definition) {
            $name = strtolower((string) $key);

            if (! $this->catalog->isComposerScriptNameAllowed($name)) {
                continue;
            }

            if (! $this->catalog->isComposerScriptDefinitionSafe($definition)) {
                continue;
            }

            $names[] = $name;
        }

        return $this->uniqueSorted($names);
    }

    /**
     * @return list<string>
     */
    private function discoverNpmScriptNames(GitLabProjectReference $reference, GitLabApiCredentials $credentials): array
    {
        $raw = $this->gitLab->fetchRawFile($reference->projectPath, $reference->ref, 'package.json', $credentials);

        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $scripts = $decoded['scripts'] ?? null;

        if (! is_array($scripts)) {
            return [];
        }

        $blocked = $this->catalog->deniedNpmRunScripts();

        return $this->uniqueSorted(array_values(array_filter(
            array_map('strval', array_keys($scripts)),
            fn (string $name): bool => ! in_array(strtolower($name), $blocked, true),
        )));
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>  $fallbackComposer
     * @param  list<string>  $discoveredScripts
     * @return list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>
     */
    private function buildComposerCategories(array $fallbackComposer, array $discoveredScripts): array
    {
        $existing = [];

        foreach ($fallbackComposer as $category) {
            foreach ($category['commands'] as $entry) {
                $first = strtolower(explode(' ', trim($entry['command']), 2)[0] ?? '');

                if ($first !== '') {
                    $existing[$first] = true;
                }
            }
        }

        $extra = [];

        foreach ($discoveredScripts as $script) {
            $key = strtolower($script);

            if (isset($existing[$key])) {
                continue;
            }

            $extra[] = [
                'label' => 'composer '.$script,
                'command' => $script,
            ];
        }

        if ($extra === []) {
            return $fallbackComposer;
        }

        $categories = $fallbackComposer;
        array_unshift($categories, [
            'id' => 'project-git-composer',
            'label' => 'Script composer.json (Git)',
            'commands' => $extra,
        ]);

        return $categories;
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string, exec?: bool}>}>  $fallbackNpm
     * @param  list<string>  $discoveredScripts
     * @return list<array{id: string, label: string, commands: list<array{label: string, command: string, exec: bool}>}>
     */
    private function buildNpmCategories(array $fallbackNpm, array $discoveredScripts): array
    {
        $existing = [];

        foreach ($fallbackNpm as $category) {
            foreach ($category['commands'] as $entry) {
                $command = $entry['command'] ?? '';

                if (str_starts_with($command, 'run ')) {
                    $existing[strtolower(substr($command, 4))] = true;
                }
            }
        }

        $extra = [];

        foreach ($discoveredScripts as $script) {
            $key = strtolower($script);

            if (isset($existing[$key])) {
                continue;
            }

            $longRunning = in_array($key, $this->catalog->deniedNpmRunScripts(), true);

            $extra[] = [
                'label' => 'npm run '.$script.($longRunning ? ' (terminale)' : ''),
                'command' => 'run '.$script,
                'exec' => ! $longRunning,
            ];
        }

        if ($extra === []) {
            return $fallbackNpm;
        }

        $categories = $fallbackNpm;
        array_unshift($categories, [
            'id' => 'project-git-npm',
            'label' => 'Script package.json (Git)',
            'commands' => $extra,
        ]);

        return $categories;
    }

    /**
     * @return array{
     *     command_catalog: array{artisan: list<array>, composer: list<array>, npm: list<array>},
     *     command_catalog_source: 'fallback',
     *     command_catalog_message: string,
     *     gitlab_credential_source: null,
     *     artisan_names: list<string>,
     *     composer_names: list<string>,
     *     npm_run_scripts: list<string>,
     * }
     */
    private function fallbackPayload(string $message): array
    {
        $catalog = $this->catalog->fallbackForUi();

        return [
            'command_catalog' => $catalog,
            'command_catalog_source' => 'fallback',
            'command_catalog_message' => $message,
            'gitlab_credential_source' => null,
            'artisan_names' => $this->catalog->artisanCommandNamesFromCategories($catalog['artisan']),
            'composer_names' => $this->catalog->composerCommandNamesFromCategories($catalog['composer']),
            'npm_run_scripts' => $this->catalog->npmRunScriptsFromCategories($catalog['npm']),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function uniqueSorted(array $names): array
    {
        $unique = array_values(array_unique(array_map('strtolower', $names)));
        sort($unique);

        return $unique;
    }
}
