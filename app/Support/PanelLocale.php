<?php

// PanelLocale.php — Supported panel locales and the session key that stores the choice.
//
// exports: PanelLocale | PanelLocale::supported(): array | PanelLocale::resolve(mixed $candidate): string
// used_by: app/Http/Controllers/Account/LocaleController.php
//          app/Http/Middleware/HandleInertiaRequests.php
//          app/Http/Middleware/SetLocale.php
//          app/Http/Requests/UpdateLocaleRequest.php
//          tests/Feature/LocalePreferenceTest.php
// rules:   Only it and en. Missing or unknown values resolve to Italian. Do not persist the fallback.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Session locale it|en, Italian default.

namespace App\Support;

final class PanelLocale
{
    public const string SESSION_KEY = 'locale';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return ['it', 'en'];
    }

    public static function resolve(mixed $candidate): string
    {
        if (is_string($candidate) && in_array($candidate, self::supported(), true)) {
            return $candidate;
        }

        return 'it';
    }
}
