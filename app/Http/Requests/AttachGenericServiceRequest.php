<?php

// AttachGenericServiceRequest.php — Authorize creating a generic Dokploy application.
//
// exports: AttachGenericServiceRequest | AttachGenericServiceRequest::authorize(): bool | AttachGenericServiceRequest::rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   readonly members MUST fail authorize (403) on mutate routes; admin bypasses pivot role.
// agent:   grok-4.7 | cursor | 2026-10-21 | s_generic_service | Button creates a Dokploy app without a stack preset

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class AttachGenericServiceRequest extends FormRequest
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
