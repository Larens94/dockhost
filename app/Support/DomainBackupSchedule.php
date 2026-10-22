<?php

// DomainBackupSchedule.php — Dokploy-compatible cron presets for domain DB backups.
//
// exports: DomainBackupSchedule | DomainBackupSchedule::defaultCron(): string | DomainBackupSchedule::presetsForInertia(): list<array{value: string, label: string}> | DomainBackupSchedule::normalize(?string $schedule, ?string $legacyFrequency = null): string | DomainBackupSchedule::isAllowed(string $cron): bool
// used_by: app/Http/Controllers/DomainController.php
//         app/Services/Hosting/DokployDomainBackupService.php
// rules:   Cron strings match Dokploy backup UI presets; custom must be five-field cron.
// agent:   composer-2.5-fast | cursor | 2026-10-22 | s_dokploy_domain_backup | Backup schedule presets
// message:

namespace App\Support;

class DomainBackupSchedule
{
    public const DEFAULT = '0 3 * * *';

    /**
     * @return list<array{cron: string, legacy: string|null, label_key: string}>
     */
    private static function presetDefinitions(): array
    {
        return [
            ['cron' => '0 3 * * *', 'legacy' => 'daily', 'label_key' => 'daily_3am'],
            ['cron' => '0 3 * * 0', 'legacy' => 'weekly', 'label_key' => 'weekly_sunday_3am'],
            ['cron' => '0 0 * * *', 'legacy' => null, 'label_key' => 'daily_midnight'],
            ['cron' => '0 0 * * 0', 'legacy' => null, 'label_key' => 'weekly_sunday_midnight'],
            ['cron' => '0 0 * * 1-5', 'legacy' => null, 'label_key' => 'weekdays_midnight'],
            ['cron' => '0 0 1 * *', 'legacy' => null, 'label_key' => 'monthly_first'],
            ['cron' => '0 * * * *', 'legacy' => null, 'label_key' => 'hourly'],
            ['cron' => '*/15 * * * *', 'legacy' => null, 'label_key' => 'every_15_minutes'],
            ['cron' => '* * * * *', 'legacy' => null, 'label_key' => 'every_minute'],
        ];
    }

    public static function defaultCron(): string
    {
        return self::DEFAULT;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function presetsForInertia(): array
    {
        $rows = [];

        foreach (self::presetDefinitions() as $preset) {
            $cron = $preset['cron'];
            $label = __('panel.domains.show.backup_schedule_presets.'.$preset['label_key']);

            $rows[] = [
                'value' => $cron,
                'label' => $label.' ('.$cron.')',
            ];
        }

        return $rows;
    }

    public static function normalize(?string $schedule, ?string $legacyFrequency = null): string
    {
        $schedule = is_string($schedule) ? trim($schedule) : '';

        if ($schedule !== '' && self::isAllowed($schedule)) {
            return $schedule;
        }

        if ($legacyFrequency === 'weekly') {
            return '0 3 * * 0';
        }

        if ($legacyFrequency === 'daily') {
            return self::DEFAULT;
        }

        return self::DEFAULT;
    }

    public static function isAllowed(string $cron): bool
    {
        $cron = trim($cron);

        if ($cron === '') {
            return false;
        }

        foreach (self::presetDefinitions() as $preset) {
            if ($preset['cron'] === $cron) {
                return true;
            }
        }

        return (bool) preg_match(
            '/^(\*|\d+|\*\/\d+|\d+-\d+)(\s+(\*|\d+|\*\/\d+|\d+-\d+)){4}$/',
            $cron,
        );
    }
}
