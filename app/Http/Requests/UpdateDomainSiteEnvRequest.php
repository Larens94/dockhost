<?php

// UpdateDomainSiteEnvRequest.php — Validates merged env updates for a domain Dokploy application.
//
// exports: UpdateDomainSiteEnvRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   readonly members MUST fail authorize; stack must have Dokploy application. Never accept raw env blob — keyed entries only.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | FormRequest for site env merge.

namespace App\Http\Requests;

use App\Enums\DomainStack;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDomainSiteEnvRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        if (! $this->userCanMutateDomainHosting()) {
            return false;
        }

        $stack = $this->domainFromRoute()->stack ?? DomainStack::None;

        return $stack->createsApplication();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.key' => ['required', 'string', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/', 'max:128'],
            'entries.*.value' => ['nullable', 'string', 'max:8192'],
        ];
    }

    /**
     * @return list<array{key: string, value: string|null}>
     */
    public function normalizedEntries(): array
    {
        $entries = [];

        foreach ($this->validated('entries') as $entry) {
            $entries[] = [
                'key' => strtoupper(trim((string) $entry['key'])),
                'value' => array_key_exists('value', $entry) && $entry['value'] !== null
                    ? (string) $entry['value']
                    : '',
            ];
        }

        return $entries;
    }
}
