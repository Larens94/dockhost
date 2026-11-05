<?php

// MutateDomainSftpUserRequest.php — Authorizes delete for a domain SFTP user.
//
// exports: MutateDomainSftpUserRequest | MutateDomainSftpUserRequest::authorize(): bool | MutateDomainSftpUserRequest::rules(): array | MutateDomainSftpUserRequest::userBelongsToDomain(Domain $domain, SftpUser $sftpUser): void
// used_by: app/Http/Controllers/DomainController.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-11-05 | s_sftp_user_delete | Delete mistaken extra SFTP users

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Models\Domain;
use App\Models\SftpUser;
use Illuminate\Foundation\Http\FormRequest;

class MutateDomainSftpUserRequest extends FormRequest
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

    public function userBelongsToDomain(Domain $domain, SftpUser $sftpUser): void
    {
        if ((int) $sftpUser->domain_id !== (int) $domain->id) {
            abort(404);
        }
    }
}
