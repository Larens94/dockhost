<?php

// LaravelArtisanSignatureParser.php — Extract Artisan command names from PHP source (no runtime).
//
// exports: LaravelArtisanSignatureParser | LaravelArtisanSignatureParser::fromCommandClassSource(string $source): list<string> | LaravelArtisanSignatureParser::fromConsoleRoutesSource(string $source): list<string>
// used_by: app/Services/Laravel/LaravelToolkitCommandDiscovery.php
//         tests/Unit/LaravelArtisanSignatureParserTest.php
// rules:   Returns base command names only (segment before first space or {). Does not execute PHP.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Regex parse $signature and Artisan::command().
// message:

namespace App\Services\Laravel;

class LaravelArtisanSignatureParser
{
    /**
     * @return list<string>
     */
    public function fromCommandClassSource(string $source): array
    {
        $names = [];

        if (preg_match_all(
            '/(?:protected\s+(?:\??string\s+)?)?\$signature\s*=\s*[\'"]([^\'"]+)[\'"]/m',
            $source,
            $matches,
        ) && $matches[1] !== []) {
            foreach ($matches[1] as $signature) {
                $name = $this->baseCommandName($signature);

                if ($name !== null) {
                    $names[] = $name;
                }
            }
        }

        return $this->uniqueSorted($names);
    }

    /**
     * @return list<string>
     */
    public function fromConsoleRoutesSource(string $source): array
    {
        $names = [];

        if (preg_match_all(
            '/Artisan::command\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
            $source,
            $matches,
        ) && $matches[1] !== []) {
            foreach ($matches[1] as $signature) {
                $name = $this->baseCommandName($signature);

                if ($name !== null) {
                    $names[] = $name;
                }
            }
        }

        return $this->uniqueSorted($names);
    }

    private function baseCommandName(string $signature): ?string
    {
        $signature = trim($signature);

        if ($signature === '') {
            return null;
        }

        $segment = preg_split('/[\s{]+/', $signature, 2)[0] ?? '';

        $segment = strtolower(trim($segment));

        if ($segment === '' || ! preg_match('/^[a-z0-9][a-z0-9:_-]*$/', $segment)) {
            return null;
        }

        return $segment;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function uniqueSorted(array $names): array
    {
        $unique = array_values(array_unique($names));
        sort($unique);

        return $unique;
    }
}
