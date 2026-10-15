<?php

// SetPanelAdminCommandTest.php — SetPanelAdminCommandTest module.
//
// exports: SetPanelAdminCommandTest
// used_by: none
// rules:   none
// agent:   composer-2.5 | cursor | 2026-10-15 | panel_admin_cli

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetPanelAdminCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_admin_from_options(): void
    {
        $email = 'cli-admin@panel.test';
        $plain = 'CliAdminPass9';

        $this->artisan('dokhosts:set-panel-admin', [
            '--email' => $email,
            '--password' => $plain,
        ])->assertSuccessful();

        $user = User::query()->where('email', $email)->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check($plain, $user->password));
    }

    public function test_updates_existing_admin_password(): void
    {
        $email = 'existing@panel.test';
        User::factory()->create([
            'email' => $email,
            'password' => 'old-secret',
            'is_admin' => true,
        ]);

        $this->artisan('dokhosts:set-panel-admin', [
            '--email' => $email,
            '--password' => 'new-secret',
        ])->assertSuccessful();

        $user = User::query()->where('email', $email)->firstOrFail();

        $this->assertTrue(Hash::check('new-secret', $user->password));
        $this->assertSame(1, User::query()->where('email', $email)->count());
    }

    public function test_reads_admin_email_and_password_from_env(): void
    {
        $this->setEnv('ADMIN_EMAIL', 'env-admin@panel.test');
        $this->setEnv('ADMIN_PASSWORD', 'FromEnvPass1');

        $this->artisan('dokhosts:set-panel-admin')->assertSuccessful();

        $user = User::query()->where('email', 'env-admin@panel.test')->firstOrFail();

        $this->assertTrue(Hash::check('FromEnvPass1', $user->password));
    }

    /**
     * @param  non-empty-string  $key
     */
    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
