<?php

// DokployApplicationEnv.php — Parse and merge Dokploy application env text blobs.
//
// exports: DokployApplicationEnv | DokployApplicationEnv::assignments(string $env): array | DokployApplicationEnv::replaceAssignments(string $currentEnv, array $replacements): array
// used_by: app/Services/Dokploy/PanelGitLabCredentialSync.php
//         app/Services/Dokploy/PanelSmtpSettingsSync.php
// rules:   Never log env values — keys only in return metadata. Preserve comments and blank lines on replace.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | Shared env line parser for panel GitLab sync.
// message:

namespace App\Services\Dokploy;

class DokployApplicationEnv
{
    /**
     * @return array<string, string>
     */
    public function assignments(string $env): array
    {
        $assignments = [];

        foreach (preg_split("/\r\n|\n|\r/", $env) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (! str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key !== '') {
                $assignments[$key] = $value;
            }
        }

        return $assignments;
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{env: string, added: list<string>, updated: list<string>}
     */
    public function replaceAssignments(string $currentEnv, array $replacements): array
    {
        if ($replacements === []) {
            $env = rtrim(str_replace(["\r\n", "\r"], "\n", $currentEnv), "\n");

            return [
                'env' => $env === '' ? '' : $env."\n",
                'added' => [],
                'updated' => [],
            ];
        }

        $lines = preg_split("/\r\n|\n|\r/", $currentEnv) ?: [];
        $rewritten = [];
        $seen = [];
        $added = [];
        $updated = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                $rewritten[] = $line;

                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key === '' || ! array_key_exists($key, $replacements)) {
                $rewritten[] = $line;

                continue;
            }

            $seen[$key] = true;

            if ($value === $replacements[$key]) {
                $rewritten[] = $key.'='.$value;

                continue;
            }

            $updated[] = $key;
            $rewritten[] = $key.'='.$replacements[$key];
        }

        foreach ($replacements as $key => $value) {
            if (isset($seen[$key])) {
                continue;
            }

            $added[] = $key;
            $rewritten[] = $key.'='.$value;
        }

        $env = rtrim(implode("\n", $rewritten), "\n");

        return [
            'env' => $env === '' ? '' : $env."\n",
            'added' => $added,
            'updated' => $updated,
        ];
    }
}
