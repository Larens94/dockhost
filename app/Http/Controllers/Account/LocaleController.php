<?php

// LocaleController.php — Session language preference for authenticated panel users.
//
// exports: LocaleController | LocaleController::show(): Response | LocaleController::update(UpdateLocaleRequest $request): RedirectResponse
// used_by: routes/web.php
// rules:   Store the choice in the session only. Do not add a users.locale column.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Account language screen writes session locale.

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocaleRequest;
use App\Support\PanelLocale;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocaleController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Account/Locale');
    }

    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = PanelLocale::resolve($request->validated('locale'));

        $request->session()->put(PanelLocale::SESSION_KEY, $locale);
        app()->setLocale($locale);

        return redirect()
            ->route('account.locale.show')
            ->with('success', __('panel.locale_settings.saved'));
    }
}
