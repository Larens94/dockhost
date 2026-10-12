<?php

// StoreDomainAliasRequest.php — Validates alias/subdomain creation for a domain.
//
// exports: StoreDomainAliasRequest | authorize(): bool | rules(): array | aliasAttributes(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   Mutate hosting only. Multi-tenancy default: web on, redirect_301 off.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Store alias FormRequest
// message:

namespace App\Http\Requests;

use App\Enums\DomainAliasKind;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDomainAliasRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(DomainAliasKind::class)],
            'fqdn' => ['required_if:kind,alias', 'nullable', 'string', 'max:255'],
            'subdomain' => ['required_if:kind,subdomain', 'nullable', 'string', 'max:63'],
            'web_service' => ['sometimes', 'boolean'],
            'redirect_301' => ['sometimes', 'boolean'],
            'mail_service' => ['sometimes', 'boolean'],
            'sync_dns' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     fqdn?: string|null,
     *     subdomain?: string|null,
     *     web_service: bool,
     *     redirect_301: bool,
     *     mail_service: bool,
     *     sync_dns: bool
     * }
     */
    public function aliasAttributes(): array
    {
        $validated = $this->validated();

        return [
            'kind' => $validated['kind'],
            'fqdn' => $validated['fqdn'] ?? null,
            'subdomain' => $validated['subdomain'] ?? null,
            'web_service' => (bool) ($validated['web_service'] ?? true),
            'redirect_301' => (bool) ($validated['redirect_301'] ?? false),
            'mail_service' => (bool) ($validated['mail_service'] ?? false),
            'sync_dns' => (bool) ($validated['sync_dns'] ?? false),
        ];
    }
}
