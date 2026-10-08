<?php

// UpdateLocaleRequest.php — Validates a panel language change for the current session.
//
// exports: UpdateLocaleRequest | UpdateLocaleRequest::authorize(): bool | UpdateLocaleRequest::rules(): array | UpdateLocaleRequest::messages(): array
// used_by: app/Http/Controllers/Account/LocaleController.php
// rules:   Authenticated users only. locale must be it or en.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_panel_locale | Reject locales outside it|en.

namespace App\Http\Requests;

use App\Support\PanelLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
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
            'locale' => ['required', 'string', Rule::in(PanelLocale::supported())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'locale.required' => __('panel.locale_settings.invalid'),
            'locale.in' => __('panel.locale_settings.invalid'),
        ];
    }
}
