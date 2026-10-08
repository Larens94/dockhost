<?php

// PanelSmtpSettingsTest.php — Panel SMTP principale page, Dokploy sync, access control.
//
// exports: PanelSmtpSettingsTest
// used_by: none
// rules:   Http::fake Dokploy; never embed real passwords in source; assert password absent from response body.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | SMTP save encrypts password and syncs MAIL_*.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_465 | OVH 465/ssl preset + container env overrides DB merge.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_save_truth | POST persists port; formPayload + merge prefer panel_settings over container env.

namespace Tests\Feature;

use App\Models\PanelSetting;
use App\Models\User;
use App\Services\Panel\PanelSmtpSettingsStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PanelSmtpSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_open_smtp_page(): void
    {
        $this->get(route('panel.smtp.index'))->assertRedirect(route('login'));
    }

    public function test_member_gets_404_on_smtp_page(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->get(route('panel.smtp.index'))
            ->assertNotFound();
    }

    public function test_admin_can_store_smtp_and_syncs_dokploy_without_password_in_response(): void
    {
        Config::set('dokploy.url', 'https://dokploy.test');
        Config::set('dokploy.api_key', 'test-key');
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => "APP_KEY=base64:existing\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $admin = User::factory()->create();
        $secretPassword = 'smtp-secret-password-xyz';

        $response = $this->actingAs($admin)
            ->post(route('panel.smtp.update'), [
                'provider_preset' => PanelSmtpSettingsStore::PRESET_GENERIC,
                'mail_host' => 'mail.example.com',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'noreply@example.com',
                'mail_password' => $secretPassword,
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'DokHosts Test',
            ]);

        $response->assertRedirect(route('panel.smtp.index'));
        $this->assertStringNotContainsString($secretPassword, (string) $response->getContent());

        Http::assertSent(function (Request $request) use ($secretPassword): bool {
            if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                return false;
            }

            $env = (string) ($request->data()['env'] ?? '');

            return str_contains($env, 'MAIL_HOST=mail.example.com')
                && str_contains($env, 'MAIL_MAILER=smtp')
                && str_contains($env, 'MAIL_PASSWORD='.$secretPassword);
        });

        $encrypted = PanelSetting::value(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED);
        $this->assertIsString($encrypted);
        $this->assertSame($secretPassword, Crypt::decryptString($encrypted));
        $this->assertSame('mail.example.com', PanelSetting::value(PanelSmtpSettingsStore::KEY_HOST));
    }

    public function test_member_cannot_store_smtp_settings(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->post(route('panel.smtp.update'), [
                'provider_preset' => PanelSmtpSettingsStore::PRESET_OVH,
                'mail_host' => 'ssl0.ovh.net',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'user@test.test',
                'mail_password' => 'secret-12345678',
                'mail_from_address' => 'user@test.test',
                'mail_from_name' => 'Test',
            ])
            ->assertNotFound();
    }

    public function test_blank_password_keeps_existing_encrypted_value(): void
    {
        Config::set('dokploy.url', 'https://dokploy.test');
        Config::set('dokploy.api_key', 'test-key');
        Config::set('dokploy.self_application_id', 'panel-app');

        PanelSetting::put(PanelSmtpSettingsStore::KEY_HOST, 'mail.example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PORT, '587');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_ENCRYPTION, 'tls');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_USERNAME, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_ADDRESS, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_NAME, 'DokHosts');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED, Crypt::encryptString('existing-smtp-pass'));

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => '',
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('panel.smtp.update'), [
                'provider_preset' => PanelSmtpSettingsStore::PRESET_GENERIC,
                'mail_host' => 'mail.example.com',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'noreply@example.com',
                'mail_password' => '',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'DokHosts Updated',
            ])
            ->assertRedirect(route('panel.smtp.index'));

        $this->assertSame('existing-smtp-pass', Crypt::decryptString((string) PanelSetting::value(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED)));
        $this->assertSame('DokHosts Updated', PanelSetting::value(PanelSmtpSettingsStore::KEY_FROM_NAME));
    }

    public function test_stored_panel_settings_override_container_env_on_merge(): void
    {
        PanelSetting::put(PanelSmtpSettingsStore::KEY_HOST, 'ssl0.ovh.net');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PORT, '465');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_ENCRYPTION, 'ssl');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_USERNAME, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_ADDRESS, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_NAME, 'DokHosts');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED, Crypt::encryptString('stored-smtp-pass'));

        Config::set('mail.mailers.smtp.host', 'ssl0.ovh.net');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.scheme', null);

        $previousPort = getenv('MAIL_PORT');
        $previousEncryption = getenv('MAIL_ENCRYPTION');

        putenv('MAIL_PORT=587');
        putenv('MAIL_ENCRYPTION=tls');
        $_ENV['MAIL_PORT'] = '587';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';

        try {
            app(PanelSmtpSettingsStore::class)->mergeStoredIntoConfig();
        } finally {
            if ($previousPort === false) {
                putenv('MAIL_PORT');
                unset($_ENV['MAIL_PORT']);
            } else {
                putenv('MAIL_PORT='.$previousPort);
                $_ENV['MAIL_PORT'] = $previousPort;
            }

            if ($previousEncryption === false) {
                putenv('MAIL_ENCRYPTION');
                unset($_ENV['MAIL_ENCRYPTION']);
            } else {
                putenv('MAIL_ENCRYPTION='.$previousEncryption);
                $_ENV['MAIL_ENCRYPTION'] = $previousEncryption;
            }
        }

        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_smtp_form_payload_shows_stored_port_despite_stale_container_env(): void
    {
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PRESET, PanelSmtpSettingsStore::PRESET_GENERIC);
        PanelSetting::put(PanelSmtpSettingsStore::KEY_HOST, 'ssl0.ovh.net');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PORT, '465');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_ENCRYPTION, 'ssl');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_USERNAME, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_ADDRESS, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_NAME, 'DokHosts');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED, Crypt::encryptString('stored-smtp-pass'));

        $previousPort = getenv('MAIL_PORT');
        $previousEncryption = getenv('MAIL_ENCRYPTION');

        putenv('MAIL_PORT=587');
        putenv('MAIL_ENCRYPTION=tls');
        $_ENV['MAIL_PORT'] = '587';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';

        try {
            $payload = app(PanelSmtpSettingsStore::class)->formPayload();
        } finally {
            if ($previousPort === false) {
                putenv('MAIL_PORT');
                unset($_ENV['MAIL_PORT']);
            } else {
                putenv('MAIL_PORT='.$previousPort);
                $_ENV['MAIL_PORT'] = $previousPort;
            }

            if ($previousEncryption === false) {
                putenv('MAIL_ENCRYPTION');
                unset($_ENV['MAIL_ENCRYPTION']);
            } else {
                putenv('MAIL_ENCRYPTION='.$previousEncryption);
                $_ENV['MAIL_ENCRYPTION'] = $previousEncryption;
            }
        }

        $this->assertSame('465', $payload['mail_port']);
        $this->assertSame('ssl', $payload['mail_encryption']);
    }

    public function test_admin_smtp_save_shows_submitted_port_on_next_page_despite_container_env(): void
    {
        Config::set('dokploy.url', 'https://dokploy.test');
        Config::set('dokploy.api_key', 'test-key');
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => "MAIL_PORT=587\nMAIL_ENCRYPTION=tls\n",
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $previousPort = getenv('MAIL_PORT');
        $previousEncryption = getenv('MAIL_ENCRYPTION');
        putenv('MAIL_PORT=587');
        putenv('MAIL_ENCRYPTION=tls');
        $_ENV['MAIL_PORT'] = '587';
        $_ENV['MAIL_ENCRYPTION'] = 'tls';

        $admin = User::factory()->create();
        $password = 'smtp-save-port-pass1';

        try {
            $this->actingAs($admin)
                ->post(route('panel.smtp.update'), [
                    'provider_preset' => PanelSmtpSettingsStore::PRESET_GENERIC,
                    'mail_host' => 'ssl0.ovh.net',
                    'mail_port' => 465,
                    'mail_encryption' => 'ssl',
                    'mail_username' => 'noreply@example.com',
                    'mail_password' => $password,
                    'mail_from_address' => 'noreply@example.com',
                    'mail_from_name' => 'DokHosts',
                ])
                ->assertRedirect(route('panel.smtp.index'));

            $this->assertSame('465', PanelSetting::value(PanelSmtpSettingsStore::KEY_PORT));

            Http::assertSent(function (Request $request): bool {
                if ($request->url() !== 'https://dokploy.test/api/application.saveEnvironment') {
                    return false;
                }

                $env = (string) ($request->data()['env'] ?? '');

                return str_contains($env, 'MAIL_PORT=465') && str_contains($env, 'MAIL_ENCRYPTION=ssl');
            });

            $this->actingAs($admin)
                ->get(route('panel.smtp.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Panel/Smtp/Index')
                    ->where('settings.mail_port', '465')
                    ->where('settings.mail_encryption', 'ssl'));

            $this->actingAs($admin)
                ->post(route('panel.smtp.update'), [
                    'provider_preset' => PanelSmtpSettingsStore::PRESET_GENERIC,
                    'mail_host' => 'ssl0.ovh.net',
                    'mail_port' => 2525,
                    'mail_encryption' => 'tls',
                    'mail_username' => 'noreply@example.com',
                    'mail_password' => '',
                    'mail_from_address' => 'noreply@example.com',
                    'mail_from_name' => 'DokHosts',
                ])
                ->assertRedirect(route('panel.smtp.index'));

            $this->actingAs($admin)
                ->get(route('panel.smtp.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('settings.mail_port', '2525')
                    ->where('settings.mail_encryption', 'tls'));
        } finally {
            if ($previousPort === false) {
                putenv('MAIL_PORT');
                unset($_ENV['MAIL_PORT']);
            } else {
                putenv('MAIL_PORT='.$previousPort);
                $_ENV['MAIL_PORT'] = $previousPort;
            }

            if ($previousEncryption === false) {
                putenv('MAIL_ENCRYPTION');
                unset($_ENV['MAIL_ENCRYPTION']);
            } else {
                putenv('MAIL_ENCRYPTION='.$previousEncryption);
                $_ENV['MAIL_ENCRYPTION'] = $previousEncryption;
            }
        }
    }

    public function test_ovh_preset_stores_implicit_ssl_on_port_465(): void
    {
        Config::set('dokploy.url', 'https://dokploy.test');
        Config::set('dokploy.api_key', 'test-key');
        Config::set('dokploy.self_application_id', 'panel-app');

        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'env' => '',
            ]),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response(['ok' => true]),
        ]);

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('panel.smtp.update'), [
                'provider_preset' => PanelSmtpSettingsStore::PRESET_OVH,
                'mail_host' => 'ignored.example.com',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
                'mail_username' => 'noreply@example.com',
                'mail_password' => 'ovh-smtp-pass-12',
                'mail_from_address' => 'noreply@example.com',
                'mail_from_name' => 'DokHosts',
            ])
            ->assertRedirect(route('panel.smtp.index'));

        $this->assertSame('ssl0.ovh.net', PanelSetting::value(PanelSmtpSettingsStore::KEY_HOST));
        $this->assertSame('465', PanelSetting::value(PanelSmtpSettingsStore::KEY_PORT));
        $this->assertSame('ssl', PanelSetting::value(PanelSmtpSettingsStore::KEY_ENCRYPTION));
    }
}
