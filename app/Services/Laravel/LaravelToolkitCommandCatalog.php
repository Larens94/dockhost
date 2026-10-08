<?php

// LaravelToolkitCommandCatalog.php — Fallback Artisan/Composer/npm catalogs and deny rules for Toolkit.
//
// exports: LaravelToolkitCommandCatalog | LaravelToolkitCommandCatalog::fallbackForUi(): array | LaravelToolkitCommandCatalog::artisanCommandNames(): array | LaravelToolkitCommandCatalog::deniedArtisanCommandNames(): array | LaravelToolkitCommandCatalog::isArtisanNameDeniedByPattern(string $name): bool
// used_by: app/Services/Laravel/LaravelToolkitCommandAllowlist.php
//         app/Services/Laravel/LaravelToolkitCommandDiscovery.php
//         app/Services/Laravel/LaravelToolkitExecutor.php
// rules:   GitLab discovery merges project artisan/composer/npm from repo. Fallback is hosting-safe Laravel core only (no per-app RUNTS). Never tinker/migrate:fresh in fallback.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_pipeline_etl | pipeline-etl + queue copy-only; RUNTS import path /app/...
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | db:seed and migrate --seed in database fallback.
// message:

namespace App\Services\Laravel;

class LaravelToolkitCommandCatalog
{
    /**
     * @return array{artisan: list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>, composer: list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>, npm: list<array{id: string, label: string, commands: list<array{label: string, command: string, exec: bool}>}>}
     */
    public function fallbackForUi(): array
    {
        return [
            'artisan' => $this->translateCategoryLabels('artisan', self::FALLBACK_ARTISAN_CATEGORIES),
            'composer' => $this->translateCategoryLabels('composer', self::FALLBACK_COMPOSER_CATEGORIES),
            'npm' => $this->translateCategoryLabels('npm', self::FALLBACK_NPM_CATEGORIES),
        ];
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array<string, mixed>>}>  $categories
     * @return list<array{id: string, label: string, commands: list<array<string, mixed>>}>
     */
    private function translateCategoryLabels(string $kind, array $categories): array
    {
        return array_map(function (array $category) use ($kind): array {
            $category['label'] = __('panel.toolkit.catalog.'.$kind.'.'.$category['id']);

            if ($kind === 'npm') {
                $category['commands'] = array_map(function (array $command): array {
                    if (($command['command'] ?? '') === 'run dev') {
                        $command['label'] = __('panel.toolkit.npm_dev');
                    }

                    return $command;
                }, $category['commands']);
            }

            return $category;
        }, $categories);
    }

    /**
     * @return array{artisan: list<array>, composer: list<array>, npm: list<array>}
     */
    public function forUi(): array
    {
        return $this->fallbackForUi();
    }

    /**
     * @return list<string>
     */
    public function artisanCommandNames(): array
    {
        return $this->artisanCommandNamesFromCategories(self::FALLBACK_ARTISAN_CATEGORIES);
    }

    /**
     * @return list<string>
     */
    public function composerCommandNames(): array
    {
        return $this->composerCommandNamesFromCategories(self::FALLBACK_COMPOSER_CATEGORIES);
    }

    /**
     * @return list<string>
     */
    public function npmRunScripts(): array
    {
        return $this->npmRunScriptsFromCategories(self::FALLBACK_NPM_CATEGORIES);
    }

    /**
     * @return list<string>
     */
    public function npmSubcommands(): array
    {
        return self::NPM_SUBCOMMANDS;
    }

    /**
     * @return list<string>
     */
    public function deniedArtisanCommandNames(): array
    {
        return self::ARTISAN_DENIED_NAMES;
    }

    /**
     * @return list<string>
     */
    public function deniedNpmRunScripts(): array
    {
        return self::NPM_DENIED_RUN_SCRIPTS;
    }

    public function isArtisanNameDeniedByPattern(string $name): bool
    {
        $name = strtolower($name);

        foreach (self::ARTISAN_DENIED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function deniedComposerCommandNames(): array
    {
        return self::COMPOSER_DENIED_NAMES;
    }

    public function isComposerScriptDefinitionSafe(mixed $definition): bool
    {
        $lines = is_string($definition)
            ? [$definition]
            : (is_array($definition) ? array_map('strval', $definition) : []);

        foreach ($lines as $line) {
            $line = strtolower($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/rm\s+-rf|>\s*\/|chmod\s+777|\|\s*sh|\$\(|`/', $line) === 1) {
                return false;
            }
        }

        return $lines !== [];
    }

    public function isComposerScriptNameAllowed(string $name): bool
    {
        $name = strtolower($name);

        if (in_array($name, self::COMPOSER_DENIED_NAMES, true)) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9_-]*$/', $name) === 1;
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>  $categories
     * @return list<string>
     */
    public function artisanCommandNamesFromCategories(array $categories): array
    {
        return $this->uniqueCommandNames($categories);
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>  $categories
     * @return list<string>
     */
    public function composerCommandNamesFromCategories(array $categories): array
    {
        return $this->uniqueCommandNames($categories);
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string, exec?: bool}>}>  $categories
     * @return list<string>
     */
    public function npmRunScriptsFromCategories(array $categories): array
    {
        $scripts = [];

        foreach ($categories as $category) {
            foreach ($category['commands'] as $entry) {
                if (($entry['exec'] ?? true) === false) {
                    continue;
                }

                $command = trim($entry['command'] ?? '');

                if (str_starts_with($command, 'run ')) {
                    $script = strtolower(trim(substr($command, 4)));

                    if ($script === '' || in_array($script, self::NPM_DENIED_RUN_SCRIPTS, true)) {
                        continue;
                    }

                    if (! in_array($script, $scripts, true)) {
                        $scripts[] = $script;
                    }
                }
            }
        }

        sort($scripts);

        return $scripts;
    }

    /**
     * @param  list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>  $categories
     * @return list<string>
     */
    private function uniqueCommandNames(array $categories): array
    {
        $names = [];

        foreach ($categories as $category) {
            foreach ($category['commands'] as $entry) {
                if (($entry['exec'] ?? true) === false) {
                    continue;
                }

                $first = strtolower(explode(' ', trim($entry['command']), 2)[0] ?? '');

                if ($first !== '' && ! in_array($first, $names, true)) {
                    $names[] = $first;
                }
            }
        }

        sort($names);

        return $names;
    }

    /**
     * @var list<string>
     */
    private const ARTISAN_DENIED_NAMES = [
        'tinker',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
        'db',
        'serve',
        'queue:listen',
        'queue:work',
        'schedule:work',
        'horizon',
        'octane:start',
        'octane:stop',
        'shell',
    ];

    /**
     * @var list<string>
     */
    private const ARTISAN_DENIED_PREFIXES = [
        'make:',
    ];

    /**
     * @var list<string>
     */
    private const NPM_DENIED_RUN_SCRIPTS = [
        'dev',
        'serve',
        'watch',
        'hot',
    ];

    /**
     * @var list<string>
     */
    private const COMPOSER_DENIED_NAMES = [
        'update',
        'remove',
        'require',
        'global',
        'create-project',
        'publish',
        'reinstall',
        'run-script',
    ];

    /**
     * @var list<array{id: string, label: string, commands: list<array{label: string, command: string, exec?: bool}>}>
     */
    private const FALLBACK_ARTISAN_CATEGORIES = [
        [
            'id' => 'info',
            'label' => 'Informazioni',
            'commands' => [
                ['label' => 'about', 'command' => 'about'],
                ['label' => 'env', 'command' => 'env'],
                ['label' => 'list', 'command' => 'list'],
                ['label' => 'inspire', 'command' => 'inspire'],
            ],
        ],
        [
            'id' => 'cache-config',
            'label' => 'Cache e configurazione',
            'commands' => [
                ['label' => 'optimize', 'command' => 'optimize'],
                ['label' => 'optimize:clear', 'command' => 'optimize:clear'],
                ['label' => 'cache:clear', 'command' => 'cache:clear'],
                ['label' => 'config:clear', 'command' => 'config:clear'],
                ['label' => 'config:cache', 'command' => 'config:cache'],
                ['label' => 'clear-compiled', 'command' => 'clear-compiled'],
                ['label' => 'package:discover', 'command' => 'package:discover'],
            ],
        ],
        [
            'id' => 'routes-views',
            'label' => 'Route, view ed eventi',
            'commands' => [
                ['label' => 'route:list', 'command' => 'route:list'],
                ['label' => 'route:clear', 'command' => 'route:clear'],
                ['label' => 'route:cache', 'command' => 'route:cache'],
                ['label' => 'view:clear', 'command' => 'view:clear'],
                ['label' => 'view:cache', 'command' => 'view:cache'],
                ['label' => 'event:clear', 'command' => 'event:clear'],
                ['label' => 'event:cache', 'command' => 'event:cache'],
                ['label' => 'event:list', 'command' => 'event:list'],
            ],
        ],
        [
            'id' => 'database',
            'label' => 'Database',
            'commands' => [
                ['label' => 'migrate:status', 'command' => 'migrate:status'],
                ['label' => 'migrate --force', 'command' => 'migrate --force'],
                ['label' => 'migrate --seed --force', 'command' => 'migrate --seed --force'],
                ['label' => 'migrate:rollback --force', 'command' => 'migrate:rollback --force'],
                ['label' => 'db:seed', 'command' => 'db:seed'],
                ['label' => 'auth:clear-resets', 'command' => 'auth:clear-resets'],
            ],
        ],
        [
            'id' => 'queue-schedule',
            'label' => 'Code e schedulazioni',
            'commands' => [
                ['label' => 'queue:restart', 'command' => 'queue:restart'],
                ['label' => 'queue:failed', 'command' => 'queue:failed'],
                ['label' => 'schedule:list', 'command' => 'schedule:list'],
                ['label' => 'schedule:run', 'command' => 'schedule:run'],
                ['label' => 'schedule:test', 'command' => 'schedule:test'],
                ['label' => 'schedule:clear-cache', 'command' => 'schedule:clear-cache'],
            ],
        ],
        [
            'id' => 'storage-maintenance',
            'label' => 'Storage e manutenzione',
            'commands' => [
                ['label' => 'storage:link', 'command' => 'storage:link'],
                ['label' => 'up', 'command' => 'up'],
                ['label' => 'down', 'command' => 'down'],
                ['label' => 'channel:list', 'command' => 'channel:list'],
            ],
        ],
        [
            'id' => 'inertia',
            'label' => 'Inertia (se installato)',
            'commands' => [
                ['label' => 'inertia:check-ssr', 'command' => 'inertia:check-ssr'],
                ['label' => 'inertia:stop-ssr', 'command' => 'inertia:stop-ssr'],
            ],
        ],
    ];

    /**
     * @var list<array{id: string, label: string, commands: list<array{label: string, command: string}>}>
     */
    private const FALLBACK_COMPOSER_CATEGORIES = [
        [
            'id' => 'install',
            'label' => 'Dipendenze',
            'commands' => [
                ['label' => 'install --no-dev', 'command' => 'install --no-dev'],
                ['label' => 'install --no-interaction', 'command' => 'install --no-interaction --prefer-dist --optimize-autoloader'],
                ['label' => 'dump-autoload', 'command' => 'dump-autoload'],
                ['label' => 'dump-autoload --optimize', 'command' => 'dump-autoload --optimize'],
            ],
        ],
        [
            'id' => 'verify',
            'label' => 'Verifica (sola lettura)',
            'commands' => [
                ['label' => 'validate', 'command' => 'validate'],
                ['label' => 'validate --no-check-publish', 'command' => 'validate --no-check-publish'],
            ],
        ],
    ];

    /**
     * @var list<string>
     */
    private const NPM_SUBCOMMANDS = [
        'ci',
        'install',
        'run',
    ];

    /**
     * @var list<array{id: string, label: string, commands: list<array{label: string, command: string, exec: bool}>}>
     */
    private const FALLBACK_NPM_CATEGORIES = [
        [
            'id' => 'install',
            'label' => 'Installazione',
            'commands' => [
                ['label' => 'npm ci', 'command' => 'ci', 'exec' => true],
                ['label' => 'npm install', 'command' => 'install --no-audit --no-fund', 'exec' => true],
            ],
        ],
        [
            'id' => 'scripts',
            'label' => 'Script package.json (tipici Laravel + Vite)',
            'commands' => [
                ['label' => 'npm run build', 'command' => 'run build', 'exec' => true],
                ['label' => 'npm run production', 'command' => 'run production', 'exec' => true],
                ['label' => 'npm run preview', 'command' => 'run preview', 'exec' => true],
                ['label' => 'npm run dev (terminale)', 'command' => 'run dev', 'exec' => false],
            ],
        ],
    ];
}
