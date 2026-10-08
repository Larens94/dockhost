<?php

// SendPanelSmtpTestRequest.php — Validates SMTP test email POST.
//
// exports: SendPanelSmtpTestRequest | SendPanelSmtpTestRequest::authorize(): bool | SendPanelSmtpTestRequest::rules(): array
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   Admin auth via route middleware; never accept or log SMTP password.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | test_email validation for panel SMTP test.

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendPanelSmtpTestRequest extends FormRequest
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
            'test_email' => ['required', 'string', 'max:255', 'email:rfc'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'test_email.required' => 'Inserisci l’email di prova.',
            'test_email.email' => 'L’email di prova non è valida.',
        ];
    }
}
