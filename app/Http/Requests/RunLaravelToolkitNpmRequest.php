<?php

// RunLaravelToolkitNpmRequest.php — Validates npm command POST for Laravel Toolkit.
//
// exports: RunLaravelToolkitNpmRequest | RunLaravelToolkitNpmRequest::authorize(): bool | RunLaravelToolkitNpmRequest::rules(): array
// used_by: app/Http/Controllers/LaravelToolkitController.php
// rules:   Authenticated users only; command string max 255.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_toolkit_catalog | Added npm exec request for Toolkit parity.
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class RunLaravelToolkitNpmRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        return $this->userCanMutateDomainHosting();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:255'],
        ];
    }
}
