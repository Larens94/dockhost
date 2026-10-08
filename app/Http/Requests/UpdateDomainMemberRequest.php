<?php

// UpdateDomainMemberRequest.php — Change domain member role or re-grant.
//
// exports: UpdateDomainMemberRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainMemberController.php
// rules:   Cannot set is_admin via this route — role enum only.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Role update validation.

namespace App\Http\Requests;

use App\Enums\DomainMemberRole;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDomainMemberRequest extends FormRequest
{
    use AuthorizesDomainHosting;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canManageDomainMembers($this->domainFromRoute());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(DomainMemberRole::class)],
        ];
    }
}
