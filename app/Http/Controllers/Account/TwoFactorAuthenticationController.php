<?php

// TwoFactorAuthenticationController.php — Optional TOTP 2FA setup for panel users.
//
// exports: TwoFactorAuthenticationController | show | prepare | confirm | destroy
// used_by: routes/web.php
// rules:   Setup secret lives in session until confirm. Recovery codes shown once then only hashes stored.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Account menu 2FA enable/disable.

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use App\Support\TwoFactorRecoveryCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorAuthenticationController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Account/TwoFactor', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'qrUrl' => $request->session()->get('two_factor.qr_url'),
            'setupSecret' => $request->session()->get('two_factor.plain_secret'),
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes_plain'),
        ]);
    }

    public function prepare(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return back()->withErrors(['two_factor' => __('panel.account_2fa.already_enabled')]);
        }

        $secret = Totp::generateSecret();
        $issuer = (string) config('app.name', 'DokHosts');
        $uri = Totp::provisioningUri($secret, $user->email, $issuer);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='.rawurlencode($uri);

        $request->session()->put('two_factor.pending_secret', Crypt::encryptString($secret));
        $request->session()->put('two_factor.plain_secret', $secret);
        $request->session()->put('two_factor.qr_url', $qrUrl);

        return redirect()->route('account.two-factor.show');
    }

    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $encrypted = $request->session()->get('two_factor.pending_secret');

        if (! is_string($encrypted) || $encrypted === '') {
            return back()->withErrors(['code' => __('panel.account_2fa.start_first')]);
        }

        $secret = Crypt::decryptString($encrypted);

        if (! Totp::verify($secret, $validated['code'])) {
            return back()->withErrors(['code' => __('panel.account_2fa.invalid_confirm')]);
        }

        $plainRecovery = TwoFactorRecoveryCodes::generatePlain();
        $hashedRecovery = TwoFactorRecoveryCodes::hashPlainCodes($plainRecovery);

        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $hashedRecovery,
        ])->save();

        $request->session()->forget([
            'two_factor.pending_secret',
            'two_factor.plain_secret',
            'two_factor.qr_url',
        ]);
        $request->session()->flash('two_factor.recovery_codes_plain', $plainRecovery);

        return redirect()
            ->route('account.two-factor.show')
            ->with('success', __('panel.account_2fa.enabled'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['password' => __('panel.account_2fa.bad_password')]);
        }

        if ($user->hasTwoFactorEnabled()) {
            $code = $validated['code'] ?? '';

            if ($code === '' || ! Totp::verify($user->twoFactorSecretPlain(), $code)) {
                return back()->withErrors(['code' => __('panel.account_2fa.invalid_disable')]);
            }
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget('two_factor');

        return redirect()
            ->route('account.two-factor.show')
            ->with('success', __('panel.account_2fa.disabled'));
    }
}
