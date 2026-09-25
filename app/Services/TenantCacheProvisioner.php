<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Site;
use Illuminate\Support\Str;

class TenantCacheProvisioner
{
    public function __construct(private InfrastructureDriver $driver) {}

    public function ensure(Site $site, Pool $pool): void
    {
        $dedicated = ($site->options['cache_mode'] ?? 'shared') === 'dedicated';

        if (! $dedicated) {
            return;
        }

        $meta = $site->meta ?? [];
        $existing = $meta['services']['redis']['external_id'] ?? null;

        if (is_string($existing) && $existing !== '') {
            return;
        }

        $secrets = $site->service_secrets ?? [];
        $password = is_string($secrets['redis_password'] ?? null) && $secrets['redis_password'] !== ''
            ? $secrets['redis_password']
            : Str::password(24, symbols: false);
        $appName = 'redis-'.$site->id;
        $connection = $pool->adminConnection();

        $remote = $this->driver->createCache([
            'name' => $appName,
            'app_name' => $appName,
            'password' => $password,
            'environment_id' => $connection['dokploy_environment_id'] ?? config('dockhost.dokploy.environment_id'),
            'image' => $connection['image'] ?? 'redis:7',
        ]);

        $meta['services']['redis'] = [
            'external_id' => $remote['external_id'],
            'status' => $remote['status'],
        ];
        $secrets['redis_password'] = $password;
        $secrets['redis_host'] = $remote['host'];
        $secrets['redis_port'] = 6379;
        $site->meta = $meta;
        $site->service_secrets = $secrets;
        $site->save();
    }
}
