<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Provisions a site from wizard choices.
 * Does NOT replicate Dokploy UI (deploy logs, SSL UI, cron) — only business
 * options + calls the infra driver for runtime materialization.
 */
class SiteProvisioningService
{
    public function __construct(
        private InfrastructureDriver $driver,
    ) {}

    /**
     * @param  array{
     *   client_id:int,
     *   domain:string,
     *   recipe_id:int,
     *   wants_database:bool,
     *   database_pool_id?:int|null,
     *   wants_storage:bool,
     *   storage_pool_id?:int|null,
     *   wants_sftp:bool,
     *   wants_cache:bool,
     *   cache_pool_id?:int|null
     * }  $input
     */
    public function provision(array $input): Site
    {
        $recipe = Recipe::query()->where('enabled', true)->findOrFail($input['recipe_id']);

        $poolIds = [];
        $options = [
            'wants_database' => (bool) ($input['wants_database'] ?? false),
            'database_pool_id' => null,
            'wants_storage' => (bool) ($input['wants_storage'] ?? false),
            'storage_pool_id' => null,
            'wants_sftp' => (bool) ($input['wants_sftp'] ?? false),
            'wants_cache' => (bool) ($input['wants_cache'] ?? false),
            'cache_pool_id' => null,
            'runtime_pool_id' => null,
        ];

        if ($options['wants_database']) {
            $pool = $this->requirePool($input['database_pool_id'] ?? null, 'database');
            $options['database_pool_id'] = $pool->id;
            $poolIds[] = $pool->id;
        }

        if ($options['wants_storage'] || $options['wants_sftp']) {
            // SFTP implies storage path
            $options['wants_storage'] = true;
            $pool = $this->requirePool($input['storage_pool_id'] ?? null, 'storage');
            $options['storage_pool_id'] = $pool->id;
            $poolIds[] = $pool->id;
        }

        if ($options['wants_cache']) {
            $pool = $this->requirePool($input['cache_pool_id'] ?? null, 'cache');
            $options['cache_pool_id'] = $pool->id;
            $poolIds[] = $pool->id;
        }

        $runtime = Pool::query()->where('kind', 'runtime')->orderBy('id')->first();
        if ($runtime) {
            $options['runtime_pool_id'] = $runtime->id;
            $poolIds[] = $runtime->id;
        }

        return DB::transaction(function () use ($input, $recipe, $options, $poolIds) {
            $site = Site::query()->create([
                'client_id' => $input['client_id'],
                'recipe_id' => $recipe->id,
                'domain' => $input['domain'],
                'status' => 'pending',
                'pool_ids' => array_values(array_unique($poolIds)),
                'options' => $options,
                'meta' => [
                    'provisioned_via' => 'wizard',
                    'stack' => $recipe->stack,
                ],
            ]);

            // Driver calls — Dokploy owns deploy/SSL; we only request creation.
            if ($options['wants_database']) {
                // schema/user creation is DockHost responsibility (to be wired)
            }

            $deploy = $this->driver->deployApplication([
                'domain' => $site->domain,
                'recipe' => $recipe->slug,
                'options' => $options,
            ]);

            $site->dokploy_app_id = $deploy['external_id'] ?? ('app_pending_'.$site->id);
            $site->status = 'provisioning';
            $site->save();

            $this->driver->attachDomain([
                'domain' => $site->domain,
                'application_id' => $site->dokploy_app_id,
            ]);

            foreach ($poolIds as $poolId) {
                Pool::query()->whereKey($poolId)->increment('usage');
            }

            return $site->fresh(['client', 'recipe']);
        });
    }

    private function requirePool(?int $id, string $kind): Pool
    {
        if (! $id) {
            throw ValidationException::withMessages([
                "{$kind}_pool_id" => "Select a {$kind} pool.",
            ]);
        }

        $pool = Pool::query()->whereKey($id)->where('kind', $kind)->first();
        if (! $pool) {
            throw ValidationException::withMessages([
                "{$kind}_pool_id" => "Invalid {$kind} pool.",
            ]);
        }

        if ($pool->usage >= $pool->capacity) {
            throw ValidationException::withMessages([
                "{$kind}_pool_id" => "Pool {$pool->name} is full.",
            ]);
        }

        return $pool;
    }
}
