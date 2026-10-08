<?php

// PanelSmtpSettingsStore.php — Persists panel SMTP settings and merges into runtime mail config.
//
// exports: PanelSmtpSettingsStore | PanelSmtpSettingsStore::mergeStoredIntoConfig(): void | PanelSmtpSettingsStore::isSmtpMailerReadyForDelivery(): bool | PanelSmtpSettingsStore::store(array $attributes, ?string $plainPassword): void | PanelSmtpSettingsStore::formPayload(): array | PanelSmtpSettingsStore::mailEnvAssignmentsFromConfig(): array
// used_by: app/Providers/AppServiceProvider.php
//         app/Http/Controllers/PanelSmtpSettingsController.php
//         app/Services/Dokploy/PanelSmtpSettingsSync.php
// rules:   After panel save, panel_settings is source of truth for host/port/encryption/username/from; mergeStoredIntoConfig applies stored SMTP over baked config/env for sends. Blank password on save keeps encrypted DB password. Password encrypted with Crypt::encryptString. Never log password. OVH preset is ssl0.ovh.net:465 ssl — never hardcode password.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_env_ui | formPayload + Dokploy sync prefer container MAIL_* over panel_settings.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_save_truth | POST + formPayload + Dokploy sync use DB; merge applies stored transport over container env.

namespace App\Services\Panel;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class PanelSmtpSettingsStore
{
    public const PRESET_OVH = 'ovh';

    public const PRESET_GENERIC = 'generic';

    public const KEY_PRESET = 'smtp.provider_preset';

    public const KEY_HOST = 'smtp.host';

    public const KEY_PORT = 'smtp.port';

    public const KEY_ENCRYPTION = 'smtp.encryption';

    public const KEY_USERNAME = 'smtp.username';

    public const KEY_PASSWORD_ENCRYPTED = 'smtp.password_encrypted';

    public const KEY_FROM_ADDRESS = 'smtp.from_address';

    public const KEY_FROM_NAME = 'smtp.from_name';

    /**
     * Rules: MUST run on each request before mail is sent; stored panel SMTP overrides baked config when password+host exist in DB.
     */
    public function mergeStoredIntoConfig(): void
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        if ($this->hasStoredSmtpConfiguration()) {
            $this->applyPanelStoredSmtpToConfig();

            return;
        }

        if (! filled(config('mail.mailers.smtp.password'))) {
            $password = $this->decryptedPasswordFromDatabase();
            if ($password !== null) {
                Config::set('mail.mailers.smtp.password', $password);
            }
        }

        $configuredHost = config('mail.mailers.smtp.host');
        if (! is_string($configuredHost) || $configuredHost === '' || $configuredHost === '127.0.0.1') {
            $host = PanelSetting::value(self::KEY_HOST);
            if (is_string($host) && $host !== '') {
                Config::set('mail.mailers.smtp.host', $host);
            }
        }

        $configuredPort = config('mail.mailers.smtp.port');
        if (! is_numeric($configuredPort) || (int) $configuredPort === 2525) {
            $port = PanelSetting::value(self::KEY_PORT);
            if (is_string($port) && $port !== '' && ctype_digit($port)) {
                Config::set('mail.mailers.smtp.port', (int) $port);
            }
        }

        if (! filled(config('mail.mailers.smtp.username'))) {
            $username = PanelSetting::value(self::KEY_USERNAME);
            if (is_string($username) && $username !== '') {
                Config::set('mail.mailers.smtp.username', $username);
            }
        }

        if (! filled(config('mail.from.address')) || config('mail.from.address') === 'hello@example.com') {
            $from = PanelSetting::value(self::KEY_FROM_ADDRESS);
            if (is_string($from) && $from !== '') {
                Config::set('mail.from.address', $from);
            }
        }

        if (! filled(config('mail.from.name'))) {
            $fromName = PanelSetting::value(self::KEY_FROM_NAME);
            if (is_string($fromName) && $fromName !== '') {
                Config::set('mail.from.name', $fromName);
            }
        }

        $defaultMailer = (string) config('mail.default');
        if (in_array($defaultMailer, ['log', 'array'], true) && $this->isSmtpMailerReadyForDelivery()) {
            Config::set('mail.default', 'smtp');
        }

        if (! filled(config('mail.mailers.smtp.scheme'))) {
            $encryption = $this->normalizedEncryption(PanelSetting::value(self::KEY_ENCRYPTION));
            Config::set('mail.mailers.smtp.scheme', $this->schemeForEncryption($encryption));
        }
    }

    public function isSmtpMailerReadyForDelivery(): bool
    {
        $host = config('mail.mailers.smtp.host');
        if (! is_string($host) || $host === '' || $host === '127.0.0.1') {
            $storedHost = PanelSetting::value(self::KEY_HOST);
            $host = is_string($storedHost) ? $storedHost : '';
        }

        if ($host === '') {
            return false;
        }

        if (filled(config('mail.mailers.smtp.password'))) {
            return true;
        }

        return $this->passwordConfiguredInDatabase();
    }

    /**
     * @param  array{
     *     provider_preset: string,
     *     mail_host: string,
     *     mail_port: int|string,
     *     mail_encryption: string,
     *     mail_username: string,
     *     mail_from_address: string,
     *     mail_from_name: string,
     * }  $attributes
     *
     * @throws ValidationException
     */
    public function store(array $attributes, ?string $plainPassword): void
    {
        $preset = $attributes['provider_preset'];
        $host = trim($attributes['mail_host']);
        $port = (string) $attributes['mail_port'];
        $encryption = $this->normalizedEncryption($attributes['mail_encryption']);
        $username = trim($attributes['mail_username']);
        $fromAddress = trim($attributes['mail_from_address']);
        $fromName = trim($attributes['mail_from_name']);

        if ($preset === self::PRESET_OVH) {
            $host = 'ssl0.ovh.net';
            $port = '465';
            $encryption = 'ssl';
        }

        $plainPassword = $plainPassword !== null ? trim($plainPassword) : '';
        $existingPassword = $this->decryptedPasswordFromDatabase();

        if ($plainPassword === '' && $existingPassword === null) {
            throw ValidationException::withMessages([
                'mail_password' => 'Inserisci la password SMTP (o lasciala vuota solo se già salvata).',
            ]);
        }

        $passwordToUse = $plainPassword !== '' ? $plainPassword : $existingPassword;

        PanelSetting::put(self::KEY_PRESET, $preset);
        PanelSetting::put(self::KEY_HOST, $host);
        PanelSetting::put(self::KEY_PORT, $port);
        PanelSetting::put(self::KEY_ENCRYPTION, $encryption);
        PanelSetting::put(self::KEY_USERNAME, $username);
        PanelSetting::put(self::KEY_FROM_ADDRESS, $fromAddress);
        PanelSetting::put(self::KEY_FROM_NAME, $fromName);

        if ($plainPassword !== '') {
            PanelSetting::put(self::KEY_PASSWORD_ENCRYPTED, Crypt::encryptString($plainPassword));
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $passwordToUse);
        Config::set('mail.mailers.smtp.scheme', $this->schemeForEncryption($encryption));
        Config::set('mail.from.address', $fromAddress);
        Config::set('mail.from.name', $fromName);
    }

    /**
     * @return array{
     *     provider_preset: string,
     *     mail_host: string,
     *     mail_port: string,
     *     mail_encryption: string,
     *     mail_username: string,
     *     mail_from_address: string,
     *     mail_from_name: string,
     *     password_configured: bool,
     *     can_redeploy: bool,
     * }
     */
    public function formPayload(): array
    {
        $preset = PanelSetting::value(self::KEY_PRESET) ?? self::PRESET_GENERIC;
        $host = PanelSetting::value(self::KEY_HOST);
        $port = PanelSetting::value(self::KEY_PORT);
        $encryption = PanelSetting::value(self::KEY_ENCRYPTION);
        $username = PanelSetting::value(self::KEY_USERNAME);
        $fromAddress = PanelSetting::value(self::KEY_FROM_ADDRESS);
        $fromName = PanelSetting::value(self::KEY_FROM_NAME);

        if ($preset === self::PRESET_OVH && ($host === null || $host === '')) {
            $host = 'ssl0.ovh.net';
            $port = '465';
            $encryption = 'ssl';
        }

        $payload = [
            'provider_preset' => $preset,
            'mail_host' => is_string($host) && $host !== '' ? $host : 'ssl0.ovh.net',
            'mail_port' => is_string($port) && $port !== '' ? $port : '587',
            'mail_encryption' => $this->normalizedEncryption($encryption),
            'mail_username' => is_string($username) ? $username : '',
            'mail_from_address' => is_string($fromAddress) && $fromAddress !== ''
                ? $fromAddress
                : (string) config('mail.from.address', ''),
            'mail_from_name' => is_string($fromName) && $fromName !== ''
                ? $fromName
                : (string) config('mail.from.name', config('app.name')),
            'password_configured' => $this->passwordConfiguredInDatabase(),
            'can_redeploy' => filled(config('dokploy.url')) && filled(config('dokploy.api_key'))
                && filled(config('dokploy.self_application_id')),
        ];

        if (! $this->hasStoredSmtpConfiguration()) {
            return $this->applyRuntimeMailTransportToPayload($payload);
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    public function mailEnvAssignmentsFromConfig(): array
    {
        $password = config('mail.mailers.smtp.password');
        $encryption = $this->encryptionFromRuntimeConfig();

        if (! is_string($password) || $password === '') {
            return [];
        }

        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $username = config('mail.mailers.smtp.username');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        return [
            'MAIL_MAILER' => 'smtp',
            'MAIL_HOST' => is_string($host) ? $host : '',
            'MAIL_PORT' => (string) (is_numeric($port) ? $port : ''),
            'MAIL_ENCRYPTION' => $encryption,
            'MAIL_USERNAME' => is_string($username) ? $username : '',
            'MAIL_PASSWORD' => $password,
            'MAIL_FROM_ADDRESS' => is_string($fromAddress) ? $fromAddress : '',
            'MAIL_FROM_NAME' => is_string($fromName) ? $fromName : '',
        ];
    }

    public function passwordConfiguredInDatabase(): bool
    {
        return $this->decryptedPasswordFromDatabase() !== null;
    }

    public function hasStoredSmtpConfiguration(): bool
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        $host = PanelSetting::value(self::KEY_HOST);

        return is_string($host) && $host !== '' && $this->passwordConfiguredInDatabase();
    }

    private function decryptedPasswordFromDatabase(): ?string
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $encrypted = PanelSetting::value(self::KEY_PASSWORD_ENCRYPTED);

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }

        return $plain !== '' ? $plain : null;
    }

    private function normalizedEncryption(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '' || $value === 'null' || $value === 'none') {
            return 'null';
        }

        if ($value === 'ssl' || $value === 'tls') {
            return $value;
        }

        return 'tls';
    }

    private function schemeForEncryption(string $encryption): ?string
    {
        return match ($encryption) {
            'ssl' => 'smtps',
            default => null,
        };
    }

    private function encryptionFromRuntimeConfig(): string
    {
        $scheme = config('mail.mailers.smtp.scheme');
        if ($scheme === 'smtps') {
            return 'ssl';
        }

        $stored = PanelSetting::value(self::KEY_ENCRYPTION);
        if (is_string($stored) && $stored !== '') {
            return $this->normalizedEncryption($stored);
        }

        return 'tls';
    }

    private function applyPanelStoredSmtpToConfig(): void
    {
        $host = PanelSetting::value(self::KEY_HOST);
        $port = PanelSetting::value(self::KEY_PORT);
        $encryption = $this->normalizedEncryption(PanelSetting::value(self::KEY_ENCRYPTION));
        $username = PanelSetting::value(self::KEY_USERNAME);
        $fromAddress = PanelSetting::value(self::KEY_FROM_ADDRESS);
        $fromName = PanelSetting::value(self::KEY_FROM_NAME);
        $password = $this->decryptedPasswordFromDatabase();

        if (is_string($host) && $host !== '') {
            Config::set('mail.mailers.smtp.host', $host);
        }

        if (is_string($port) && $port !== '' && ctype_digit($port)) {
            Config::set('mail.mailers.smtp.port', (int) $port);
        }

        Config::set('mail.mailers.smtp.scheme', $this->schemeForEncryption($encryption));

        if (is_string($username) && $username !== '') {
            Config::set('mail.mailers.smtp.username', $username);
        }

        if ($password !== null) {
            Config::set('mail.mailers.smtp.password', $password);
        }

        if (is_string($fromAddress) && $fromAddress !== '') {
            Config::set('mail.from.address', $fromAddress);
        }

        if (is_string($fromName) && $fromName !== '') {
            Config::set('mail.from.name', $fromName);
        }

        Config::set('mail.default', 'smtp');
    }

    /**
     * @param  array{
     *     provider_preset: string,
     *     mail_host: string,
     *     mail_port: string,
     *     mail_encryption: string,
     *     mail_username: string,
     *     mail_from_address: string,
     *     mail_from_name: string,
     *     password_configured: bool,
     *     can_redeploy: bool,
     * }  $payload
     * @return array{
     *     provider_preset: string,
     *     mail_host: string,
     *     mail_port: string,
     *     mail_encryption: string,
     *     mail_username: string,
     *     mail_from_address: string,
     *     mail_from_name: string,
     *     password_configured: bool,
     *     can_redeploy: bool,
     * }
     */
    private function applyRuntimeMailTransportToPayload(array $payload): array
    {
        $runtimeHost = config('mail.mailers.smtp.host');
        if (is_string($runtimeHost) && $runtimeHost !== '' && $runtimeHost !== '127.0.0.1') {
            $payload['mail_host'] = $runtimeHost;
        }

        $runtimePort = config('mail.mailers.smtp.port');
        if (is_numeric($runtimePort)) {
            $payload['mail_port'] = (string) (int) $runtimePort;
        }

        $payload['mail_encryption'] = $this->encryptionFromRuntimeConfig();

        $runtimeUsername = config('mail.mailers.smtp.username');
        if (is_string($runtimeUsername) && $runtimeUsername !== '') {
            $payload['mail_username'] = $runtimeUsername;
        }

        return $payload;
    }
}
