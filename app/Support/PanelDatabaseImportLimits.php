<?php

// PanelDatabaseImportLimits.php — Effective SQL dump upload limits for the panel container.
//
// exports: PanelDatabaseImportLimits | PanelDatabaseImportLimits::maxKilobytes(): int | PanelDatabaseImportLimits::maxBytes(): int | PanelDatabaseImportLimits::inertiaPayload(): array | PanelDatabaseImportLimits::uploadErrorMessage(UploadedFile $file): string
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Requests/ImportDomainDatabaseDumpRequest.php
// rules:   Never log file contents. Compare sizes using file bytes vs effective PHP/post cap.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_db_import_limits | Panel upload limit helpers
// message:

namespace App\Support;

use Illuminate\Http\UploadedFile;

class PanelDatabaseImportLimits
{
    public static function maxKilobytes(): int
    {
        $configured = max(1, (int) config('panel.database_import_max_kilobytes', 102400));
        $iniCap = (int) floor(self::iniMinimumBytes() / 1024);

        if ($iniCap <= 0) {
            return $configured;
        }

        return min($configured, max(1, $iniCap));
    }

    public static function maxBytes(): int
    {
        return self::maxKilobytes() * 1024;
    }

    /**
     * @return array{max_kilobytes: int, max_bytes: int, max_label: string, php_upload_max_filesize: string, php_post_max_size: string}
     */
    public static function inertiaPayload(): array
    {
        $maxBytes = self::maxBytes();

        return [
            'max_kilobytes' => self::maxKilobytes(),
            'max_bytes' => $maxBytes,
            'max_label' => self::formatBytes($maxBytes),
            'php_upload_max_filesize' => (string) ini_get('upload_max_filesize'),
            'php_post_max_size' => (string) ini_get('post_max_size'),
        ];
    }

    public static function uploadErrorMessage(UploadedFile $file): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('panel.domains.show.import_dump_too_large', [
                'max' => self::formatBytes(self::maxBytes()),
            ]),
            UPLOAD_ERR_PARTIAL => __('panel.domains.show.import_dump_upload_partial'),
            UPLOAD_ERR_NO_FILE => __('panel.domains.show.import_dump_upload_missing'),
            default => __('panel.domains.show.import_dump_upload_failed', [
                'code' => (string) $file->getError(),
            ]),
        };
    }

    private static function iniMinimumBytes(): int
    {
        $upload = self::parseIniSize((string) ini_get('upload_max_filesize'));
        $post = self::parseIniSize((string) ini_get('post_max_size'));

        if ($upload <= 0 && $post <= 0) {
            return 0;
        }

        if ($upload <= 0) {
            return $post;
        }

        if ($post <= 0) {
            return $upload;
        }

        return min($upload, $post);
    }

    private static function parseIniSize(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return match ($unit) {
            'g' => (int) round($number * 1024 * 1024 * 1024),
            'm' => (int) round($number * 1024 * 1024),
            'k' => (int) round($number * 1024),
            default => (int) round((float) $value),
        };
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            $mb = $bytes / (1024 * 1024);

            return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.').' MB';
        }

        if ($bytes >= 1024) {
            $kb = $bytes / 1024;

            return rtrim(rtrim(number_format($kb, 0, '.', ''), '0'), '.').' KB';
        }

        return $bytes.' B';
    }
}
