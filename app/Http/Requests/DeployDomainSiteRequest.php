<?php

// DeployDomainSiteRequest.php — Authorizes panel deploy for one domain application.
//
// exports: DeployDomainSiteRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   readonly members MUST fail authorize; only domains with Dokploy application id.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | FormRequest for Deploy sito.

namespace App\Http\Requests;

use App\Enums\DomainStack;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class DeployDomainSiteRequest extends FormRequest
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
        return [];
    }
}
