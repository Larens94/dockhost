<?php

// SmtpExceptionMessageSanitizer.php — Strips SMTP secrets from exception messages for UI flash.
//
// exports: SmtpExceptionMessageSanitizer | SmtpExceptionMessageSanitizer::sanitize(string $message): string
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   MUST redact mail.mailers.smtp.password and MAIL_PASSWORD env if present in message — never flash passwords.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | Redact SMTP password from error flashes.

namespace App\Services\Panel;

class SmtpExceptionMessageSanitizer
{
    /**
     * Rules: Replace known SMTP password values with [redacted] before showing to admin.
     */
    public function sanitize(string $message): string
    {
        $secrets = [];

        $configPassword = config('mail.mailers.smtp.password');
        if (is_string($configPassword) && $configPassword !== '') {
            $secrets[] = $configPassword;
        }

        $envPassword = env('MAIL_PASSWORD');
        if (is_string($envPassword) && $envPassword !== '') {
            $secrets[] = $envPassword;
        }

        foreach (array_unique($secrets) as $secret) {
            $message = str_replace($secret, '[redacted]', $message);
        }

        return $message;
    }
}
