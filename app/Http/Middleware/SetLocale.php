<?php

// SetLocale.php — Applies the session locale before Inertia shares translations.
//
// exports: SetLocale | SetLocale::handle(Request $request, Closure $next): Response
// used_by: bootstrap/app.php
// rules:   Read session key locale. Unknown or missing values use Italian. Do not write the session here.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Web middleware sets app locale from the session.

namespace App\Http\Middleware;

use App\Support\PanelLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(PanelLocale::resolve($request->session()->get(PanelLocale::SESSION_KEY)));

        return $next($request);
    }
}
