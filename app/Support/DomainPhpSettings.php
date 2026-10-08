<?php

// DomainPhpSettings.php — Per-domain PHP limits (panel) mapped to Dokploy env keys.
//
// exports: DomainPhpSettings | DomainPhpSettings::DEFAULTS | DomainPhpSettings::resolved(?array $stored): array | DomainPhpSettings::toDokployEnv(array $settings): array | DomainPhpSettings::validationRules(): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Requests/UpdateDomainPhpSettingsRequest.php
//         app/Services/Dokploy/DokployApplicationAttacher.php
// rules:   memory_limit is web (PHP_MEMORY_LIMIT); artisan_memory_limit sets RUNTS_SYNC_MEMORY_LIMIT and ARTISAN_MEMORY_LIMIT.
//          Never echo env secrets from Dokploy — only these public tuning keys.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | Plesk-like PHP settings → Dokploy env merge.

namespace App\Support;

final class DomainPhpSettings
{
    /**
     * @var array<string, string|int>
     */
    public const DEFAULTS = [
        'memory_limit' => '256M',
        'upload_max_filesize' => '64M',
        'post_max_size' => '64M',
        'max_execution_time' => 120,
        'max_input_time' => 120,
        'artisan_memory_limit' => '512M',
    ];

    /**
     * @return array<string, string|int>
     */
    public static function resolved(?array $stored): array
    {
        if ($stored === null || $stored === []) {
            return self::DEFAULTS;
        }

        $merged = array_merge(self::DEFAULTS, $stored);

        foreach (['max_execution_time', 'max_input_time'] as $intKey) {
            if (isset($merged[$intKey])) {
                $merged[$intKey] = (int) $merged[$intKey];
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public static function toDokployEnv(array $settings): array
    {
        $resolved = self::resolved($settings);
        $artisan = (string) $resolved['artisan_memory_limit'];

        return [
            'PHP_MEMORY_LIMIT' => (string) $resolved['memory_limit'],
            'UPLOAD_MAX_FILESIZE' => (string) $resolved['upload_max_filesize'],
            'POST_MAX_SIZE' => (string) $resolved['post_max_size'],
            'MAX_EXECUTION_TIME' => (string) $resolved['max_execution_time'],
            'MAX_INPUT_TIME' => (string) $resolved['max_input_time'],
            'RUNTS_SYNC_MEMORY_LIMIT' => $artisan,
            'ARTISAN_MEMORY_LIMIT' => $artisan,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        $memory = ['required', 'string', 'regex:/^\d+[KMG]?$/i'];

        return [
            'memory_limit' => $memory,
            'upload_max_filesize' => $memory,
            'post_max_size' => $memory,
            'max_execution_time' => ['required', 'integer', 'min:1', 'max:86400'],
            'max_input_time' => ['required', 'integer', 'min:1', 'max:86400'],
            'artisan_memory_limit' => $memory,
            'deploy_now' => ['sometimes', 'boolean'],
        ];
    }
}
