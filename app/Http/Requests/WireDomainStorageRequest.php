<?php

// WireDomainStorageRequest.php — Authorize panel storage wire (SFTP + Laravel public link).
//
// exports: WireDomainStorageRequest | WireDomainStorageRequest::authorize(): bool | WireDomainStorageRequest::rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   Hosting mutate permission only.
// agent:   composer-2.5-fast | cursor | 2026-11-17 | s_storage_wire

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class WireDomainStorageRequest extends FormRequest
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
            'deploy_now' => ['sometimes', 'boolean'],
        ];
    }

    public function deployNow(): bool
    {
        return (bool) $this->boolean('deploy_now', true);
    }
}
