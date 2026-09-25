<?php

namespace App\Services;

use App\Jobs\ProvisionSiteJob;
use App\Models\Client;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reserves tenant capacity, then runs the recipe job.
 * Dokploy keeps deploy, SSL, logs, and cron.
 */
class SiteProvisioningService
{
    public function __construct(
        private EntitlementGate $entitlements,
        private PoolLedger $ledger,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{
     *   client_id:int,
     *   domain:string,
     *   recipe_id:int,
     *   repository?:string|null,
     *   wants_database:bool,
     *   database_pool_id?:int|null,
     *   wants_storage:bool,
     *   storage_pool_id?:int|null,
     *   wants_sftp:bool,
     *   wants_cache:bool,
     *   cache_pool_id?:int|null,
     *   database_mode?:string|null,
     *   git_branch?:string|null
     * }  $input
     */
    public function provision(array $input): Site
    {
        $site = $this->reserve($input);
        ProvisionSiteJob::dispatchSync($site->id);

        return $site->fresh(['client', 'recipe', 'databaseAccount', 'sftpAccount']);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function reserve(array $input): Site
    {
        $recipe = Recipe::query()->where('enabled', true)->findOrFail($input['recipe_id']);
        $client = Client::query()->findOrFail($input['client_id']);

        $options = [
            'wants_database' => (bool) ($input['wants_database'] ?? false),
            'database_pool_id' => null,
            'wants_storage' => (bool) ($input['wants_storage'] ?? false),
            'storage_pool_id' => null,
            'wants_sftp' => (bool) ($input['wants_sftp'] ?? false),
            'wants_cache' => (bool) ($input['wants_cache'] ?? false),
            'cache_pool_id' => null,
            'cache_mode' => ($input['cache_mode'] ?? 'shared') === 'dedicated' ? 'dedicated' : 'shared',
            'wants_object_storage' => (bool) ($input['wants_object_storage'] ?? false),
            'runtime_pool_id' => null,
            'database_mode' => ($input['database_mode'] ?? 'shared') === 'dedicated' ? 'dedicated' : 'shared',
            'git_branch' => $input['git_branch'] ?? 'main',
            'git_ssh_key_id' => $input['git_ssh_key_id'] ?? null,
        ];

        if ($options['wants_sftp']) {
            $options['wants_storage'] = true;
        }

        $this->entitlements->assertCanProvision($client, $recipe, $options);

        $attachments = [];

        if ($options['wants_database']) {
            $pool = $this->requirePool($input['database_pool_id'] ?? null, 'database');
            $options['database_pool_id'] = $pool->id;
            $attachments['database'] = $pool->id;
        }

        if ($options['wants_storage']) {
            $pool = $this->requirePool($input['storage_pool_id'] ?? null, 'storage');
            $options['storage_pool_id'] = $pool->id;
            $attachments['storage'] = $pool->id;
        }

        if ($options['wants_cache']) {
            $pool = $this->requirePool($input['cache_pool_id'] ?? null, 'cache');
            $options['cache_pool_id'] = $pool->id;
            $attachments['cache'] = $pool->id;
        }

        $runtime = Pool::query()->where('kind', 'runtime')->orderBy('usage')->orderBy('id')->first();

        if (! $runtime) {
            throw ValidationException::withMessages([
                'recipe_id' => 'No runtime pool is available.',
            ]);
        }

        if ($runtime->usage >= $runtime->capacity) {
            throw ValidationException::withMessages([
                'recipe_id' => "Runtime pool {$runtime->name} is full.",
            ]);
        }

        $options['runtime_pool_id'] = $runtime->id;
        $attachments['runtime'] = $runtime->id;

        return DB::transaction(function () use ($input, $recipe, $options, $attachments) {
            $site = Site::query()->create([
                'client_id' => $input['client_id'],
                'recipe_id' => $recipe->id,
                'domain' => $input['domain'],
                'repository' => $input['repository'] ?? null,
                'status' => 'pending',
                'options' => $options,
                'meta' => [
                    'provisioned_via' => 'wizard',
                    'stack' => $recipe->stack,
                ],
            ]);

            $this->ledger->attach($site, $attachments);
            $this->audit->log('site.reserved', $site, ['domain' => $site->domain]);

            return $site;
        });
    }

    /**
     * Re-hold capacity and run the recipe again. Pool selection may change; data is not moved.
     *
     * @param  array<string, mixed>  $input
     */
    public function retry(Site $site, array $input = []): Site
    {
        if ($site->status !== 'failed') {
            throw ValidationException::withMessages([
                'site' => 'Only a failed site can be retried.',
            ]);
        }

        $site->loadMissing(['client', 'recipe']);

        if (! $site->client || ! $site->recipe) {
            throw ValidationException::withMessages([
                'site' => 'Site is missing its client or recipe.',
            ]);
        }

        $options = $site->options ?? [];
        $attachments = [];

        if (! empty($options['wants_database'])) {
            $pool = $this->requirePool(
                isset($input['database_pool_id']) ? (int) $input['database_pool_id'] : (int) ($options['database_pool_id'] ?? 0),
                'database',
            );
            $options['database_pool_id'] = $pool->id;
            $attachments['database'] = $pool->id;
        }

        if (! empty($options['wants_storage']) || ! empty($options['wants_sftp'])) {
            $pool = $this->requirePool(
                isset($input['storage_pool_id']) ? (int) $input['storage_pool_id'] : (int) ($options['storage_pool_id'] ?? 0),
                'storage',
            );
            $options['storage_pool_id'] = $pool->id;
            $attachments['storage'] = $pool->id;
        }

        if (! empty($options['wants_cache'])) {
            $pool = $this->requirePool(
                isset($input['cache_pool_id']) ? (int) $input['cache_pool_id'] : (int) ($options['cache_pool_id'] ?? 0),
                'cache',
            );
            $options['cache_pool_id'] = $pool->id;
            $attachments['cache'] = $pool->id;
        }

        $runtimeId = (int) ($options['runtime_pool_id'] ?? 0);
        $runtime = $runtimeId
            ? Pool::query()->whereKey($runtimeId)->where('kind', 'runtime')->first()
            : Pool::query()->where('kind', 'runtime')->orderBy('usage')->orderBy('id')->first();

        if (! $runtime) {
            throw ValidationException::withMessages([
                'site' => 'No runtime pool is available.',
            ]);
        }

        if ($runtime->usage >= $runtime->capacity) {
            throw ValidationException::withMessages([
                'site' => "Runtime pool {$runtime->name} is full.",
            ]);
        }

        $options['runtime_pool_id'] = $runtime->id;
        $attachments['runtime'] = $runtime->id;

        if (! empty($input['database_mode'])) {
            $options['database_mode'] = $input['database_mode'] === 'dedicated' ? 'dedicated' : 'shared';
        }

        $this->entitlements->assertCanProvision($site->client, $site->recipe, $options);

        DB::transaction(function () use ($site, $options, $attachments) {
            $site->options = $options;
            $site->status = 'pending';
            $site->last_error = null;
            $site->save();
            $this->ledger->sync($site, $attachments);
            $this->audit->log('site.retried', $site, ['domain' => $site->domain]);
        });

        ProvisionSiteJob::dispatchSync($site->id);

        return $site->fresh(['client', 'recipe', 'databaseAccount', 'sftpAccount']);
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
