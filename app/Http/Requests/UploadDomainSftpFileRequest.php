<?php

// UploadDomainSftpFileRequest.php — Validates SFTP jail file upload from domain panel.
//
// exports: UploadDomainSftpFileRequest
// used_by: app/Http/Controllers/DomainSftpFileController.php
// agent:   composer-2.5-fast | cursor | 2026-11-05 | s_sftp_files

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesDomainHosting;
use App\Services\Infra\SftpJailFileManager;
use Illuminate\Foundation\Http\FormRequest;

class UploadDomainSftpFileRequest extends FormRequest
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
        $maxKilobytes = (int) (SftpJailFileManager::MAX_UPLOAD_BYTES / 1024);

        return [
            'path' => ['nullable', 'string', 'max:512'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes],
        ];
    }
}
