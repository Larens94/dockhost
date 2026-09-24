<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class PoolLedger
{
    /**
     * @param  array<string, int|null>  $purposeToPoolId
     */
    public function attach(Site $site, array $purposeToPoolId): void
    {
        $poolIds = [];

        foreach ($purposeToPoolId as $purpose => $poolId) {
            if (! $poolId) {
                continue;
            }

            $match = [
                'site_id' => $site->id,
                'pool_id' => $poolId,
                'purpose' => $purpose,
            ];

            if (! DB::table('site_pools')->where($match)->exists()) {
                DB::table('site_pools')->insert([
                    ...$match,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $poolIds[] = (int) $poolId;
        }

        $site->pool_ids = array_values(array_unique($poolIds));
        $site->usage_held = true;
        $site->save();

        $this->recalculate($poolIds);
    }

    /**
     * Replace every pool attachment and hold capacity again.
     *
     * @param  array<string, int|null>  $purposeToPoolId
     */
    public function sync(Site $site, array $purposeToPoolId): void
    {
        $previous = $this->poolIds($site);
        DB::table('site_pools')->where('site_id', $site->id)->delete();
        $this->attach($site, $purposeToPoolId);
        $this->recalculate($previous);
    }

    public function release(Site $site): void
    {
        $poolIds = $this->poolIds($site);
        $site->usage_held = false;
        $site->save();
        $this->recalculate($poolIds);
    }

    /**
     * @param  list<int>  $poolIds
     */
    public function recalculate(array $poolIds): void
    {
        foreach (array_unique($poolIds) as $poolId) {
            $usage = DB::table('site_pools')
                ->join('sites', 'sites.id', '=', 'site_pools.site_id')
                ->where('site_pools.pool_id', $poolId)
                ->where('sites.usage_held', true)
                ->distinct()
                ->count('site_pools.site_id');

            Pool::query()->whereKey($poolId)->update(['usage' => $usage]);
        }
    }

    /**
     * @return list<int>
     */
    public function poolIds(Site $site): array
    {
        $ids = $site->pools()->pluck('pools.id')->all();

        if ($ids === []) {
            $ids = $site->pool_ids ?? [];
        }

        return array_values(array_map('intval', $ids));
    }
}
