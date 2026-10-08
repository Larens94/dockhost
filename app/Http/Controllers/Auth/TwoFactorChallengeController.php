<?php

// TwoFactorChallengeController.php — Second step after password login when 2FA enabled.
//
// exports: TwoFactorChallengeController | create | store
// used_by: routes/web.php
// rules:   Session key login.two_factor_user_id must exist — no full auth until code verified.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | TOTP challenge after password.

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorRecoveryCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('login.two_factor_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('login.two_factor_user_id');

        if (! is_int($userId) && ! is_string($userId)) {
            throw ValidationException::withMessages([
                'code' => __('panel.auth.session_expired'),
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = User::query()->find($userId);

        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('login.two_factor_user_id');

            throw ValidationException::withMessages([
                'code' => __('panel.auth.session_invalid'),
            ]);
        }

        $code = str_replace(' ', '', $validated['code']);
        $authenticated = false;

        if (preg_match('/^\d{6}$/', $code) && Totp::verify($user->twoFactorSecretPlain(), $code)) {
            $authenticated = true;
        } elseif (str_contains($code, '-') || strlen($code) >= 8) {
            $hashed = $user->two_factor_recovery_codes ?? [];
            $result = TwoFactorRecoveryCodes::consume($code, is_array($hashed) ? $hashed : []);

            if ($result !== null) {
                $user->forceFill(['two_factor_recovery_codes' => $result['remaining']])->save();
                $authenticated = true;
            }
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'code' => __('panel.auth.invalid_code'),
            ]);
        }

        $remember = (bool) $request->session()->pull('login.two_factor_remember', false);
        $request->session()->forget('login.two_factor_user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $home = ! $user->isAdmin()
            ? route('domains.index')
            : route('customers.index');

        return redirect()->intended($home);
    }
}
