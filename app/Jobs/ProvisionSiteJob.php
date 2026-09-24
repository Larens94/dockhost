<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\RecipeExecutor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProvisionSiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $siteId) {}

    public function handle(RecipeExecutor $executor): void
    {
        $site = Site::query()->find($this->siteId);

        if (! $site || $site->status === 'active') {
            return;
        }

        $executor->execute($site);
    }
}
