<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Site;
use Illuminate\Support\Str;

class TenantObjectStorageProvisioner
{
    public function __construct(private InfrastructureDriver $driver) {}

    public function ensure(Site $site): void
    {
        $meta = $site->meta ?? [];
        $existing = $meta['services']['minio']['external_id'] ?? null;

        if (is_string($existing) && $existing !== '') {
            return;
        }

        $secrets = $site->service_secrets ?? [];
        $accessKey = is_string($secrets['minio_access_key'] ?? null) && $secrets['minio_access_key'] !== ''
            ? $secrets['minio_access_key']
            : 'dockhost';
        $secretKey = is_string($secrets['minio_secret_key'] ?? null) && $secrets['minio_secret_key'] !== ''
            ? $secrets['minio_secret_key']
            : Str::password(24, symbols: false);
        $appName = 'minio-'.$site->id;

        $remote = $this->driver->deployCompose([
            'name' => $appName,
            'app_name' => $appName,
            'compose' => $this->compose($secretKey),
            'environment_id' => config('dockhost.dokploy.environment_id'),
        ]);

        $meta['services']['minio'] = [
            'external_id' => $remote['external_id'],
            'status' => is_string($remote['external_id'] ?? null) && ! str_starts_with((string) $remote['external_id'], 'local_')
                ? 'provisioned'
                : 'reserved',
        ];
        $secrets['minio_access_key'] = $accessKey;
        $secrets['minio_secret_key'] = $secretKey;
        $secrets['minio_endpoint'] = 'http://'.$appName.':9000';
        $site->meta = $meta;
        $site->service_secrets = $secrets;
        $site->save();
    }

    private function compose(string $secretKey): string
    {
        $secret = str_replace(["\n", '"'], '', $secretKey);

        return <<<YAML
services:
  minio:
    image: minio/minio:latest
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: dockhost
      MINIO_ROOT_PASSWORD: "{$secret}"
    volumes:
      - minio:/data
volumes:
  minio:
YAML;
    }
}
