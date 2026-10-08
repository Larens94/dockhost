<?php

// PanelGitLabCredentialController.php — Saves panel GitLab PAT and syncs Dokploy env.
//
// exports: PanelGitLabCredentialController | PanelGitLabCredentialController::store(StorePanelGitLabCredentialRequest $request, PanelGitLabCredentialStore $store, GitLabTokenValidator $validator, PanelGitLabCredentialSync $sync): JsonResponse
// used_by: routes/web.php
// rules:   Never log token. Validate with GitLab /user before persist. JSON for Toolkit UI fetch.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | Collega GitLab button backend.
// message:

namespace App\Http\Controllers;

use App\Http\Requests\StorePanelGitLabCredentialRequest;
use App\Services\Dokploy\PanelGitLabCredentialSync;
use App\Services\GitLab\GitLabTokenValidator;
use App\Services\Panel\PanelGitLabCredentialStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PanelGitLabCredentialController extends Controller
{
    public function store(
        StorePanelGitLabCredentialRequest $request,
        PanelGitLabCredentialStore $store,
        GitLabTokenValidator $validator,
        PanelGitLabCredentialSync $sync,
    ): JsonResponse {
        $url = rtrim((string) $request->validated('gitlab_url'), '/');
        $token = (string) $request->validated('token');

        if (! $validator->validate($url, $token)) {
            throw ValidationException::withMessages([
                'token' => 'GitLab ha rifiutato il token (controlla URL, scope read_api/read_repository e scadenza).',
            ]);
        }

        $store->store($url, $token);
        $syncResult = $sync->syncFromConfig();

        return response()->json([
            'ok' => true,
            'message' => 'Credenziali GitLab salvate per il pannello.',
            'sync' => $syncResult,
            'redeploy_hint' => $syncResult['status'] === 'synced'
                ? 'Env aggiornato su Dokploy: ridistribuisci dokhosts se il container non ha ancora GITLAB_TOKEN.'
                : null,
        ]);
    }
}
