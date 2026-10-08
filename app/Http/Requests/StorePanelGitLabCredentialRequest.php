<?php

// StorePanelGitLabCredentialRequest.php — Validates panel GitLab credential form POST.
//
// exports: StorePanelGitLabCredentialRequest | StorePanelGitLabCredentialRequest::authorize(): bool | StorePanelGitLabCredentialRequest::rules(): array
// used_by: app/Http/Controllers/PanelGitLabCredentialController.php
// rules:   Admin auth only; token never returned in response.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | GITLAB URL + PAT validation rules.
// message:

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePanelGitLabCredentialRequest extends FormRequest
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
            'gitlab_url' => ['required', 'string', 'max:255', 'url:http,https'],
            'token' => ['required', 'string', 'min:8', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gitlab_url.required' => __('panel.validation.gitlab_url_required'),
            'gitlab_url.url' => __('panel.validation.gitlab_url_invalid'),
            'token.required' => __('panel.validation.gitlab_token_required'),
            'token.min' => __('panel.validation.gitlab_token_short'),
        ];
    }
}
