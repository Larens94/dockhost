<?php

// SyncPanelGitlabEnvCommand.php — Writes GITLAB_* from env into Dokploy panel application.
//
// exports: SyncPanelGitlabEnvCommand | SyncPanelGitlabEnvCommand::handle(PanelGitLabCredentialSync $sync): int
// used_by: docker/entrypoint.sh
//         .gitlab-ci.yml
// rules:   Never print GITLAB_TOKEN. Exit 0 on skip-no-token (entrypoint-safe). Exit 1 only on Dokploy API failure when token was present.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_auto_sync | dokhosts:sync-panel-gitlab-env for CI bootstrap.
// message:

namespace App\Console\Commands;

use App\Services\Dokploy\PanelGitLabCredentialSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dokhosts:sync-panel-gitlab-env {--dry-run : Mostra l’esito senza chiamare saveEnvironment}')]
#[Description('Sincronizza GITLAB_URL e GITLAB_TOKEN nell’env Dokploy dell’application pannello dokhosts')]
class SyncPanelGitlabEnvCommand extends Command
{
    public function handle(PanelGitLabCredentialSync $sync): int
    {
        if ($this->option('dry-run')) {
            $tokenPresent = filled(config('services.gitlab.token'));
            $dokployPresent = filled(config('dokploy.url')) && filled(config('dokploy.api_key'));
            $this->line('GITLAB_TOKEN presente: '.($tokenPresent ? 'sì' : 'no'));
            $this->line('Dokploy API configurata: '.($dokployPresent ? 'sì' : 'no'));
            $this->line('Application id: '.(string) (config('dokploy.self_application_id') ?: '(discovery)'));

            return self::SUCCESS;
        }

        $result = $sync->syncFromConfig();

        $this->line($result['message']);

        if ($result['added_keys'] !== [] || $result['updated_keys'] !== []) {
            $this->line('Chiavi: '.implode(', ', [...$result['added_keys'], ...$result['updated_keys']]));
        }

        return match ($result['status']) {
            'failed' => self::FAILURE,
            default => self::SUCCESS,
        };
    }
}
