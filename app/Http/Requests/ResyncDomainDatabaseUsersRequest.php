<?php

// ResyncDomainDatabaseUsersRequest.php — Authorizes MariaDB resync for a domain.
//
// exports: ResyncDomainDatabaseUsersRequest | ResyncDomainDatabaseUsersRequest::authorize(): bool
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-10-09 | s_pma_native_pass | Resync site DB users to MariaDB
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use Illuminate\Foundation\Http\FormRequest;

class ResyncDomainDatabaseUsersRequest extends FormRequest
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
