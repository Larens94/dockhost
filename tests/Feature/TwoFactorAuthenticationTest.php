<?php

// TwoFactorAuthenticationTest.php — Optional panel 2FA login and setup flows.
//
// exports: TwoFactorAuthenticationTest
// used_by: none
// rules:   Never assert on raw two_factor_secret in responses.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Feature coverage for TOTP login.

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_with_two_factor_redirects_to_challenge(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create(['password' => 'password']);
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_two_factor_challenge_completes_login(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create(['password' => 'password']);
        $user->forceFill([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $code = Totp::oneTimeCode($secret);

        $this->post('/login/two-factor', ['code' => $code])
            ->assertRedirect(route('customers.index'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_account_two_factor_prepare_requires_auth(): void
    {
        $this->post('/account/two-factor/prepare')->assertRedirect(route('login'));
    }
}
