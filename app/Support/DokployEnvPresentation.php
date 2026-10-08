<?php

// DokployEnvPresentation.php — Redact Dokploy env keys for panel display (MCP-aligned).
//
// exports: DokployEnvPresentation | DokployEnvPresentation::isSensitiveKey(string $key): bool | DokployEnvPresentation::variablesForPanel(string $env): list<array{key: string, redacted: bool, value?: string, has_value?: bool}>
// used_by: app/Services/Hosting/DomainSiteEnvManager.php
// rules:   Never pass raw sensitive values to Inertia — redacted keys omit value. Match MCP-ish patterns: PASSWORD, SECRET, TOKEN, KEY, MYSQL_, AWS_SECRET.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | Panel env redaction for domain show.

namespace App\Support;

use App\Services\Dokploy\DokployApplicationEnv;

class DokployEnvPresentation
{
    public function __construct(
        private DokployApplicationEnv $envParser,
    ) {}

    public function isSensitiveKey(string $key): bool
    {
        $upper = strtoupper($key);

        if (str_starts_with($upper, 'MYSQL_')) {
            return true;
        }

        if (str_contains($upper, 'AWS_SECRET')) {
            return true;
        }

        foreach (['PASSWORD', 'SECRET', 'TOKEN', 'KEY'] as $needle) {
            if (str_contains($upper, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{key: string, redacted: bool, value?: string, has_value?: bool}>
     */
    public function variablesForPanel(string $env): array
    {
        $variables = [];

        foreach ($this->envParser->assignments($env) as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $variables[] = [
                    'key' => $key,
                    'redacted' => true,
                    'has_value' => trim($value) !== '',
                ];

                continue;
            }

            $variables[] = [
                'key' => $key,
                'redacted' => false,
                'value' => $value,
            ];
        }

        usort($variables, fn (array $a, array $b): int => strcmp($a['key'], $b['key']));

        return $variables;
    }
}
