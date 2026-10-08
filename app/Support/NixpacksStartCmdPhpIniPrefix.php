<?php

// NixpacksStartCmdPhpIniPrefix.php — Prefix NIXPACKS_START_CMD to write dokhosts.ini from env.
//
// exports: NixpacksStartCmdPhpIniPrefix | NixpacksStartCmdPhpIniPrefix::SEPARATOR | NixpacksStartCmdPhpIniPrefix::apply(?string $startCmd): ?string | NixpacksStartCmdPhpIniPrefix::stripPrefix(string $startCmd): string | NixpacksStartCmdPhpIniPrefix::iniWriterShell(): string
// used_by: app/Services/Dokploy/DokployApplicationAttacher.php
// rules:   Empty start command stays empty — never invent nginx/php-fpm stack. Idempotent: strip old prefix before re-applying. Uses PHP_* env vars already on Dokploy.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_php_ini_start | Shell prefix for Nixpacks PHP/Laravel containers.

namespace App\Support;

final class NixpacksStartCmdPhpIniPrefix
{
    public const SEPARATOR = '__DOKHOSTS_INI__&&__';

    /**
     * Rules: null/blank in → null/blank out. Never double-prefix.
     */
    public static function apply(?string $startCmd): ?string
    {
        if ($startCmd === null) {
            return null;
        }

        $trimmed = trim($startCmd);
        if ($trimmed === '') {
            return $startCmd;
        }

        $core = self::stripPrefix($trimmed);

        return self::iniWriterShell().self::SEPARATOR.$core;
    }

    public static function stripPrefix(string $startCmd): string
    {
        $position = strpos($startCmd, self::SEPARATOR);

        if ($position === false) {
            return $startCmd;
        }

        return substr($startCmd, $position + strlen(self::SEPARATOR));
    }

    /**
     * Rules: prefer $PHP_INI_DIR/conf.d when present, else /usr/local/etc/php/conf.d. Values from Dokploy env.
     */
    public static function iniWriterShell(): string
    {
        return 'for _dokhosts_d in "${PHP_INI_DIR}/conf.d" /usr/local/etc/php/conf.d; do '
            .'[ -d "$_dokhosts_d" ] && _dokhosts_ini="$_dokhosts_d/dokhosts.ini" && break; '
            .'done; '
            .'[ -n "${_dokhosts_ini:-}" ] && printf \'%s\\n\' '
            .'"memory_limit=${PHP_MEMORY_LIMIT:-256M}" '
            .'"upload_max_filesize=${UPLOAD_MAX_FILESIZE:-64M}" '
            .'"post_max_size=${POST_MAX_SIZE:-64M}" '
            .'"max_execution_time=${MAX_EXECUTION_TIME:-120}" '
            .'"max_input_time=${MAX_INPUT_TIME:-120}" '
            .'> "$_dokhosts_ini"';
    }
}
