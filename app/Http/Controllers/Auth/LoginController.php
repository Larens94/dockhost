<?php

// LoginController.php — LoginController module.
//
// exports: LoginController | LoginController::create(): Response | LoginController::store(Request $request): RedirectResponse | LoginController::destroy(Request $request): RedirectResponse
// used_by: routes/web.php
// rules:   Non-admin intended URL MUST be domains.index — not customers (404 for members).
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Post-login redirect by is_admin.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Password step then TOTP when enabled.

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Credenziali non valide.',
            ]);
        }

        $user = $request->user();

        if ($user instanceof User && $user->hasTwoFactorEnabled()) {
            $request->session()->put('login.two_factor_user_id', $user->getKey());
            $request->session()->put('login.two_factor_remember', $request->boolean('remember'));
            Auth::logout();

            return redirect()->route('two-factor.login');
        }

        $request->session()->regenerate();

        $home = $user instanceof User && ! $user->isAdmin()
            ? route('domains.index')
            : route('customers.index');

        return redirect()->intended($home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
