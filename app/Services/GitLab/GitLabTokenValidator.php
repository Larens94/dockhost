<?php

// GitLabTokenValidator.php — Validates GitLab personal access tokens against API v4.
//
// exports: GitLabTokenValidator | GitLabTokenValidator::validate(string $baseUrl, string $token): bool
// used_by: app/Services/Panel/PanelGitLabCredentialStore.php
// rules:   GET /api/v4/user only; never log token or response body. Return false on any HTTP/network failure.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | PAT check before persisting credentials.
// message:

namespace App\Services\GitLab;

use Illuminate\Support\Facades\Http;

class GitLabTokenValidator
{
    public function validate(string $baseUrl, string $token): bool
    {
        $token = trim($token);

        if ($token === '') {
            return false;
        }

        $apiBase = rtrim($baseUrl, '/').'/api/v4';

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get($apiBase.'/user');

        return $response->successful();
    }
}
