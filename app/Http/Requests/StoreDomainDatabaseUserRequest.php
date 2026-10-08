<?php

// StoreDomainDatabaseUserRequest.php — StoreDomainDatabaseUserRequest module.
//
// exports: StoreDomainDatabaseUserRequest | StoreDomainDatabaseUserRequest::authorize(): bool | StoreDomainDatabaseUserRequest::rules(): array | StoreDomainDatabaseUserRequest::messages(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Requests;

use App\Enums\DatabasePrivilege;
use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDomainDatabaseUserRequest extends FormRequest
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
            'privilege' => ['required', Rule::enum(DatabasePrivilege::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privilege.required' => __('panel.validation.privilege_required'),
        ];
    }
}
