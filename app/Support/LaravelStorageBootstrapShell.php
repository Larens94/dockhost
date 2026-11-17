<?php

// LaravelStorageBootstrapShell.php — Idempotent shell snippet wired into NIXPACKS_START_CMD for shared storage + public link.
//
// exports: LaravelStorageBootstrapShell | LaravelStorageBootstrapShell::MARKER | LaravelStorageBootstrapShell::bootstrapShell(): string | LaravelStorageBootstrapShell::isConfigured(string $startCmd): bool | LaravelStorageBootstrapShell::weaveIntoCore(string $coreStartCmd): string
// used_by: app/Services/Dokploy/DokployApplicationAttacher.php
//         app/Services/Hosting/DomainStorageWiringService.php
// rules:   Uses DOKHOSTS_STORAGE_PATH only — never deletes tenant data. Symlink storage/app/public into the app tree before migrate. umask 002 for group-writable uploads with SFTP uids.
// agent:   composer-2.5-fast | cursor | 2026-11-17 | s_storage_wire | Shared volume public tree + artisan storage:link on container start.

namespace App\Support;

final class LaravelStorageBootstrapShell
{
    public const MARKER = '_dokhosts_storage';

    public static function bootstrapShell(): string
    {
        return 'umask 002; _dokhosts_storage="${DOKHOSTS_STORAGE_PATH:-}"; '
            .'if [ -n "$_dokhosts_storage" ]; then '
            .'mkdir -p "$_dokhosts_storage/storage/app/public"; '
            .'rm -f storage/app/public 2>/dev/null || true; '
            .'ln -sfn "$_dokhosts_storage/storage/app/public" storage/app/public; '
            .'php artisan storage:link --force 2>/dev/null || true; '
            .'fi';
    }

    public static function isConfigured(string $startCmd): bool
    {
        return str_contains($startCmd, self::MARKER);
    }

    public static function weaveIntoCore(string $coreStartCmd): string
    {
        if (self::isConfigured($coreStartCmd)) {
            return $coreStartCmd;
        }

        $bootstrap = self::bootstrapShell();
        $needle = 'chmod -R a+rwx storage bootstrap/cache';
        $position = strpos($coreStartCmd, $needle);

        if ($position !== false) {
            $after = $position + strlen($needle);

            return substr($coreStartCmd, 0, $after).' && '.$bootstrap.substr($coreStartCmd, $after);
        }

        return $bootstrap.' && '.$coreStartCmd;
    }
}
