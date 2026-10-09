<?php

// MutateDomainDatabaseAccountRequest.php — Authorizes reset/delete for a domain database account.
//
// exports: MutateDomainDatabaseAccountRequest | MutateDomainDatabaseAccountRequest::authorize(): bool | MutateDomainDatabaseAccountRequest::rules(): array
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-10-09 | s_db_user_reset_delete | Reset/delete DB users on domain
// message:

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;

class MutateDomainDatabaseAccountRequest extends FormRequest
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

    public function accountBelongsToDomain(Domain $domain, DatabaseAccount $databaseAccount): void
    {
        if ((int) $databaseAccount->domain_id !== (int) $domain->id) {
            abort(404);
        }
    }
}
