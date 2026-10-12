<?php

// DestroyDomainAliasRequest.php — Authorizes alias/subdomain deletion.
//
// exports: DestroyDomainAliasRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   Mutate hosting only.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Destroy alias FormRequest
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class DestroyDomainAliasRequest extends FormRequest
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
        return [];
    }
}
