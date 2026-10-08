<?php

// HandleInertiaRequests.php — HandleInertiaRequests module.
//
// exports: HandleInertiaRequests | HandleInertiaRequests::share(Request $request): array
// used_by: none
// rules:   Flash error may contain SMTP failure text — controller MUST sanitize secrets before session flash.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | Share flash.error for SMTP test failures.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | Share flash.smtp_test_feedback for Prova invio card.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Share locale, supportedLocales, and panel translations.
// message:

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PanelLocale;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $translations = __('panel');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'locale' => app()->getLocale(),
            'supportedLocales' => array_map(
                fn (string $code): array => [
                    'code' => $code,
                    'label' => __('panel.locales.'.$code),
                ],
                PanelLocale::supported(),
            ),
            'translations' => is_array($translations) ? $translations : [],
            'auth' => [
                'user' => $request->user() instanceof User
                    ? [
                        ...$request->user()->only(['id', 'name', 'email', 'is_admin']),
                        'two_factor_enabled' => $request->user()->hasTwoFactorEnabled(),
                    ]
                    : null,
            ],
            'revealedCredential' => $request->session()->get('revealed_credential'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'smtp_test_feedback' => $request->session()->get('smtp_test_feedback'),
            ],
        ];
    }
}
