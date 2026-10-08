<?php

// UpdatePanelSmtpSettingsRequest.php — Validates panel SMTP settings form POST.
//
// exports: UpdatePanelSmtpSettingsRequest | UpdatePanelSmtpSettingsRequest::authorize(): bool | UpdatePanelSmtpSettingsRequest::rules(): array
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   Admin auth via route middleware; password never returned in response.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | SMTP principale validation rules.

namespace App\Http\Requests;

use App\Services\Panel\PanelSmtpSettingsStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePanelSmtpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider_preset' => ['required', 'string', Rule::in([
                PanelSmtpSettingsStore::PRESET_OVH,
                PanelSmtpSettingsStore::PRESET_GENERIC,
            ])],
            'mail_host' => ['required', 'string', 'max:255'],
            'mail_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_encryption' => ['required', 'string', Rule::in(['tls', 'ssl', 'null'])],
            'mail_username' => ['required', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:4096'],
            'mail_from_address' => ['required', 'string', 'max:255', 'email:rfc'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'provider_preset.required' => 'Seleziona un provider SMTP.',
            'mail_host.required' => 'Inserisci l’host SMTP.',
            'mail_port.required' => 'Inserisci la porta SMTP.',
            'mail_encryption.required' => 'Seleziona la crittografia (TLS, SSL o nessuna).',
            'mail_username.required' => 'Inserisci l’username SMTP.',
            'mail_from_address.required' => 'Inserisci l’indirizzo mittente.',
            'mail_from_address.email' => 'L’indirizzo mittente non è valido.',
            'mail_from_name.required' => 'Inserisci il nome mittente.',
        ];
    }
}
