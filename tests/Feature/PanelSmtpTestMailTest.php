<?php

// PanelSmtpTestMailTest.php — Admin SMTP test mail POST and access control.
//
// exports: PanelSmtpTestMailTest
// used_by: none
// rules:   Mail::fake only; never embed real SMTP passwords in assertions on response body.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | smtp.test route + Mail fake assertions.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_panel_fix | Reject log mailer; assert smtp when panel settings exist.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | Render mailable + assert smtp_test_feedback flash for Vue card.

namespace Tests\Feature;

use App\Mail\PanelSmtpTestMail;
use App\Models\PanelSetting;
use App\Models\User;
use App\Services\Panel\PanelSmtpSettingsStore;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelSmtpTestMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_cannot_send_smtp_test_mail(): void
    {
        $this->post(route('panel.smtp.test'), [
            'test_email' => 'test@example.com',
        ])->assertRedirect(route('login'));
    }

    public function test_member_gets_404_on_smtp_test_mail(): void
    {
        $member = User::factory()->member()->create();

        $this->actingAs($member)
            ->post(route('panel.smtp.test'), [
                'test_email' => 'test@example.com',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_send_smtp_test_mail_when_smtp_mailer_configured(): void
    {
        Mail::fake();

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'ssl0.ovh.net');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.password', 'test-smtp-password');

        $admin = User::factory()->create();
        $recipient = 'recipient@example.com';

        $response = $this->actingAs($admin)
            ->post(route('panel.smtp.test'), [
                'test_email' => $recipient,
            ]);

        $response->assertRedirect(route('panel.smtp.index'));
        $response->assertSessionHas('success');
        $this->assertStringStartsWith('Mail accettata dal server SMTP.', (string) session('success'));
        $response->assertSessionHas('smtp_test_feedback.type', 'success');

        Mail::assertSent(PanelSmtpTestMail::class, function (PanelSmtpTestMail $mail) use ($recipient): bool {
            return $mail->hasTo($recipient)
                && str_contains($mail->bodyLine, 'Il SMTP del pannello DokHosts funziona.')
                && str_contains($mail->envelope()->subject, 'DokHosts prova SMTP pannello');
        });

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'smtp.test',
        ]);
    }

    public function test_smtp_test_rejects_log_mailer_without_panel_smtp_settings(): void
    {
        Mail::fake();

        Config::set('mail.default', 'log');

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('panel.smtp.test'), [
                'test_email' => 'recipient@example.com',
            ])
            ->assertRedirect(route('panel.smtp.index'))
            ->assertSessionHas('error')
            ->assertSessionHas('smtp_test_feedback.type', 'error');

        Mail::assertNothingSent();
    }

    public function test_smtp_test_uses_smtp_when_log_default_but_panel_settings_exist(): void
    {
        Mail::fake();

        Config::set('mail.default', 'log');
        Config::set('mail.mailers.smtp.host', '127.0.0.1');
        Config::set('mail.mailers.smtp.password', null);

        PanelSetting::put(PanelSmtpSettingsStore::KEY_HOST, 'ssl0.ovh.net');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PORT, '587');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_ENCRYPTION, 'tls');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_USERNAME, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_ADDRESS, 'noreply@example.com');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_FROM_NAME, 'DokHosts');
        PanelSetting::put(PanelSmtpSettingsStore::KEY_PASSWORD_ENCRYPTED, Crypt::encryptString('panel-stored-pass'));

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('panel.smtp.test'), [
                'test_email' => 'recipient@example.com',
            ])
            ->assertRedirect(route('panel.smtp.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('smtp_test_feedback.type', 'success');

        Mail::assertSent(PanelSmtpTestMail::class);
    }

    public function test_panel_smtp_test_mail_renders_without_text_string_parameter(): void
    {
        $mail = new PanelSmtpTestMail(
            'Il SMTP del pannello DokHosts funziona.',
            '2026-09-25 22:00:00 +0200',
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Il SMTP del pannello DokHosts funziona.', $rendered);
        $this->assertStringContainsString('DokHosts prova SMTP pannello 2026-09-25 22:00:00 +0200', $mail->envelope()->subject);
    }

    public function test_smtp_test_requires_valid_email(): void
    {
        Mail::fake();

        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('panel.smtp.test'), [
                'test_email' => 'not-an-email',
            ])
            ->assertSessionHasErrors('test_email');

        Mail::assertNothingSent();
    }
}
