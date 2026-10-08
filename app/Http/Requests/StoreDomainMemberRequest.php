<?php

// StoreDomainMemberRequest.php — Validate invite/grant on domain Accessi.
//
// exports: StoreDomainMemberRequest | authorize(): bool | rules(): array
// used_by: app/Http/Controllers/DomainMemberController.php
// rules:   No password on invite — controller sends reset link. role optional; default developer.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Invite-by-email validation.

namespace App\Http\Requests;

use App\Enums\DomainMemberRole;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDomainMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(DomainMemberRole::class)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
