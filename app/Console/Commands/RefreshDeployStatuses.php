<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\RecipeExecutor;
use Illuminate\Console\Command;

class RefreshDeployStatuses extends Command
{
    protected $signature = 'dockhost:refresh-deploys';

    protected $description = 'Ask Dokploy for the status of sites that are still provisioning';

    public function handle(RecipeExecutor $executor): int
    {
        $sites = Site::query()
            ->where('status', 'provisioning')
            ->whereNotNull('dokploy_app_id')
            ->get();

        $updated = 0;

        foreach ($sites as $site) {
            if (str_starts_with((string) $site->dokploy_app_id, 'local_')) {
                continue;
            }

            $before = $site->status;
            $executor->refreshDeployStatus($site);

            if ($site->status !== $before) {
                $updated++;
            }
        }

        $this->info("Checked {$sites->count()} provisioning sites, {$updated} changed status.");

        return self::SUCCESS;
    }
}
