<?php

// LaravelToolkitCommandAllowlist.php — Validates Toolkit Artisan, Composer, and npm input.
//
// exports: LaravelToolkitCommandAllowlist | LaravelToolkitCommandAllowlist::artisan(Domain $domain, string $raw, ?array $dokployApplication): string | LaravelToolkitCommandAllowlist::composer(Domain $domain, string $raw, ?array $dokployApplication): string | LaravelToolkitCommandAllowlist::npm(Domain $domain, string $raw, ?array $dokployApplication): string | LaravelToolkitCommandAllowlist::artisanWithFallbackCatalog(string $raw): string
// used_by: app/Services/Laravel/LaravelToolkitExecutor.php
//         tests/Unit/LaravelToolkitCommandAllowlistTest.php
// rules:   Allowed names from Git discovery + fallback minus denylist. Fallback artisan = flags only (+ migrate --seed, queue:prune-failed --hours=N). Git-only artisan names allow safe option/argument tokens. No shell metacharacters.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Deduped class; removed hardcoded RUNTS; generic discovered token validation.
// message:

namespace App\Services\Laravel;

use App\Models\Domain;
use Illuminate\Validation\ValidationException;

class LaravelToolkitCommandAllowlist
{
    /**
     * @var list<string>
     */
    private const ARTISAN_FLAGS = [
        '--force',
        '--no-interaction',
        '--pretend',
        '--ansi',
        '--no-ansi',
        '--step',
        '--verbose',
        '-v',
    ];

    /**
     * @var list<string>
     */
    private const COMPOSER_FLAGS = [
        '--no-dev',
        '--no-interaction',
        '--prefer-dist',
        '--no-progress',
        '--optimize-autoloader',
        '--optimize',
        '--classmap-authoritative',
        '--no-ansi',
        '--no-check-publish',
    ];

    /**
     * @var list<string>
     */
    private const NPM_FLAGS = [
        '--no-audit',
        '--no-fund',
        '--foreground-scripts',
        '--no-progress',
    ];

    public function __construct(
        private LaravelToolkitCommandCatalog $catalog,
        private LaravelToolkitCommandDiscovery $discovery,
    ) {}

    /**
     * @param  array<string, mixed>|null  $dokployApplication
     */
    public function artisan(Domain $domain, string $raw, ?array $dokployApplication = null): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['php', 'artisan']));
        $allowed = $this->discovery->forDomain($domain, $dokployApplication)['artisan_names'];

        return $this->normalizeArtisan($tokens, $allowed);
    }

    /**
     * @param  array<string, mixed>|null  $dokployApplication
     */
    public function composer(Domain $domain, string $raw, ?array $dokployApplication = null): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['composer']));
        $allowed = $this->discovery->forDomain($domain, $dokployApplication)['composer_names'];

        return $this->normalize($tokens, $allowed, self::COMPOSER_FLAGS, 'Composer', enforceArtisanDeny: false);
    }

    /**
     * @param  array<string, mixed>|null  $dokployApplication
     */
    public function npm(Domain $domain, string $raw, ?array $dokployApplication = null): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['npm']));
        $allowedScripts = $this->discovery->forDomain($domain, $dokployApplication)['npm_run_scripts'];

        return $this->normalizeNpm($tokens, $allowedScripts);
    }

    public function artisanWithFallbackCatalog(string $raw): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['php', 'artisan']));

        return $this->normalizeArtisan($tokens, $this->catalog->artisanCommandNames());
    }

    public function composerWithFallbackCatalog(string $raw): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['composer']));

        return $this->normalize($tokens, $this->catalog->composerCommandNames(), self::COMPOSER_FLAGS, 'Composer', enforceArtisanDeny: false);
    }

    public function npmWithFallbackCatalog(string $raw): string
    {
        $tokens = $this->tokens($this->stripPrefix($raw, ['npm']));

        return $this->normalizeNpm($tokens, $this->catalog->npmRunScripts());
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $allowedScripts
     */
    private function normalizeNpm(array $tokens, array $allowedScripts): string
    {
        if ($tokens === []) {
            throw ValidationException::withMessages([
                'command' => 'npm: indica un comando consentito.',
            ]);
        }

        $subcommand = strtolower($tokens[0]);

        if ($subcommand === 'run') {
            if (! isset($tokens[1])) {
                throw ValidationException::withMessages([
                    'command' => 'npm: specifica lo script (es. run build).',
                ]);
            }

            $script = strtolower($tokens[1]);

            if (! in_array($script, $allowedScripts, true)) {
                throw ValidationException::withMessages([
                    'command' => 'npm: script «'.$tokens[1].'» non è in allowlist.',
                ]);
            }

            foreach (array_slice($tokens, 2) as $token) {
                if (! in_array($token, self::NPM_FLAGS, true)) {
                    throw ValidationException::withMessages([
                        'command' => 'npm: flag non consentito «'.$token.'».',
                    ]);
                }
            }

            return 'run '.$script.implode('', array_map(
                fn (string $flag): string => ' '.$flag,
                array_slice($tokens, 2),
            ));
        }

        if (! in_array($subcommand, $this->catalog->npmSubcommands(), true) || $subcommand === 'run') {
            throw ValidationException::withMessages([
                'command' => 'npm: «'.$tokens[0].'» non è in allowlist.',
            ]);
        }

        foreach (array_slice($tokens, 1) as $token) {
            if (! in_array($token, self::NPM_FLAGS, true)) {
                throw ValidationException::withMessages([
                    'command' => 'npm: flag non consentito «'.$token.'».',
                ]);
            }
        }

        $tokens[0] = $subcommand;

        return implode(' ', $tokens);
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $allowed
     */
    private function normalizeArtisan(array $tokens, array $allowed): string
    {
        if ($tokens === []) {
            throw ValidationException::withMessages([
                'command' => 'Artisan: indica un comando consentito.',
            ]);
        }

        $name = strtolower($tokens[0]);

        if ($this->catalog->isArtisanNameDeniedByPattern($name)
            || in_array($name, $this->catalog->deniedArtisanCommandNames(), true)) {
            throw ValidationException::withMessages([
                'command' => 'Artisan: «'.$tokens[0].'» non è consentito.',
            ]);
        }

        if (! in_array($name, $allowed, true)) {
            throw ValidationException::withMessages([
                'command' => 'Artisan: «'.$tokens[0].'» non è in allowlist (niente tinker, shell o comandi distruttivi).',
            ]);
        }

        $fallbackNames = $this->catalog->artisanCommandNames();

        if (! in_array($name, $fallbackNames, true)) {
            return $this->normalizeDiscoveredArtisan($name, $tokens);
        }

        if ($name === 'queue:monitor') {
            return $this->normalizeQueueMonitor($tokens);
        }

        return $this->normalize($tokens, $allowed, self::ARTISAN_FLAGS, 'Artisan', enforceArtisanDeny: false);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function normalizeDiscoveredArtisan(string $name, array $tokens): string
    {
        foreach (array_slice($tokens, 1) as $token) {
            if (! $this->isSafeDiscoveredArtisanToken($token)) {
                throw ValidationException::withMessages([
                    'command' => 'Artisan: argomento non consentito «'.$token.'».',
                ]);
            }
        }

        $tokens[0] = $name;

        return implode(' ', $tokens);
    }

    /**
     * @param  list<string>  $tokens
     */
    private function normalizeQueueMonitor(array $tokens): string
    {
        $queueNames = array_slice($tokens, 1);

        if (count($queueNames) !== 1 || preg_match('/^[a-zA-Z0-9_\-]+$/', $queueNames[0]) !== 1) {
            throw ValidationException::withMessages([
                'command' => 'Artisan: queue:monitor richiede un solo nome coda (es. default).',
            ]);
        }

        return 'queue:monitor '.$queueNames[0];
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function stripPrefix(string $raw, array $prefixes): string
    {
        $command = trim($raw);

        foreach ($prefixes as $prefix) {
            if (preg_match('/^'.preg_quote($prefix, '/').'\s+/i', $command) === 1) {
                $command = trim((string) preg_replace('/^'.preg_quote($prefix, '/').'\s+/i', '', $command, 1));
            }
        }

        return $command;
    }

    /**
     * @return list<string>
     */
    private function tokens(string $command): array
    {
        if ($command === '' || str_contains($command, "\0")) {
            throw ValidationException::withMessages([
                'command' => 'Comando non valido.',
            ]);
        }

        if (preg_match('/[;|&`$()<>\\\\]|\\n|\\r/', $command) === 1) {
            throw ValidationException::withMessages([
                'command' => 'Il comando non può contenere shell o metacaratteri.',
            ]);
        }

        $tokens = preg_split('/\s+/', $command) ?: [];

        return array_values(array_filter($tokens, fn (string $token): bool => $token !== ''));
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $commands
     * @param  list<string>  $flags
     */
    private function normalize(array $tokens, array $commands, array $flags, string $tool, bool $enforceArtisanDeny): string
    {
        if ($tokens === []) {
            throw ValidationException::withMessages([
                'command' => $tool.': indica un comando consentito.',
            ]);
        }

        $name = strtolower($tokens[0]);

        if ($enforceArtisanDeny && (
            $this->catalog->isArtisanNameDeniedByPattern($name)
            || in_array($name, $this->catalog->deniedArtisanCommandNames(), true)
        )) {
            throw ValidationException::withMessages([
                'command' => $tool.': «'.$tokens[0].'» non è consentito.',
            ]);
        }

        if (! $enforceArtisanDeny && in_array($name, $this->catalog->deniedComposerCommandNames(), true)) {
            throw ValidationException::withMessages([
                'command' => $tool.': «'.$tokens[0].'» non è consentito.',
            ]);
        }

        if (! in_array($name, $commands, true)) {
            throw ValidationException::withMessages([
                'command' => $tool.': «'.$tokens[0].'» non è in allowlist (niente tinker, shell o comandi distruttivi).',
            ]);
        }

        foreach (array_slice($tokens, 1) as $token) {
            if (! $this->isAllowedOption($name, $token, $flags, $tool)) {
                throw ValidationException::withMessages([
                    'command' => $tool.': flag non consentito «'.$token.'».',
                ]);
            }
        }

        $tokens[0] = $name;

        return implode(' ', $tokens);
    }

    /**
     * @param  list<string>  $globalFlags
     */
    private function isAllowedOption(string $command, string $token, array $globalFlags, string $tool): bool
    {
        if (in_array($token, $globalFlags, true)) {
            return true;
        }

        if ($tool === 'Artisan' && $command === 'migrate' && $token === '--seed') {
            return true;
        }

        if ($tool === 'Artisan' && $command === 'queue:prune-failed' && preg_match('/^--hours=\d+$/', $token) === 1) {
            return true;
        }

        return false;
    }

    private function isSafeDiscoveredArtisanToken(string $token): bool
    {
        if (str_starts_with($token, '--')) {
            return preg_match('/^--[a-z0-9][a-z0-9_-]*(?:=[^\\s;|&`$()<>\\\\]*)?$/i', $token) === 1;
        }

        if (str_contains($token, '..')) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_./:@+-]+$/', $token) === 1;
    }
}
