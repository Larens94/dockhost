<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\InfraTemplate;
use App\Models\ServiceCatalogItem;
use Illuminate\Support\Str;

class CatalogDeployer
{
    public function __construct(private InfrastructureDriver $driver) {}

    public function deployCatalog(ServiceCatalogItem $item): ServiceCatalogItem
    {
        $meta = $item->meta ?? [];

        if (is_string($meta['dokploy_ref'] ?? null) && $meta['dokploy_ref'] !== '') {
            return $item;
        }

        $slug = Str::slug($item->name);
        $password = Str::password(24, symbols: false);
        $environmentId = config('dockhost.dokploy.environment_id');

        $result = match ($item->kind) {
            'database' => $this->driver->createDatabase([
                'name' => $slug,
                'app_name' => $slug,
                'engine' => $this->engine($item->image),
                'database' => 'app',
                'username' => 'app',
                'password' => $password,
                'image' => $item->image,
                'environment_id' => $environmentId,
            ]),
            'cache' => $this->driver->createCache([
                'name' => $slug,
                'app_name' => $slug,
                'password' => $password,
                'image' => $item->image,
                'environment_id' => $environmentId,
            ]),
            default => $this->driver->deployCompose([
                'name' => $slug,
                'app_name' => $slug,
                'compose' => $this->composeFor($item, $password),
                'environment_id' => $environmentId,
            ]),
        };

        $externalId = $result['external_id'] ?? null;
        $meta['dokploy_ref'] = is_string($externalId) && $externalId !== '' ? $externalId : 'local_'.$slug;
        $meta['status'] = str_starts_with($meta['dokploy_ref'], 'local_') ? 'reserved' : 'provisioned';
        $item->meta = $meta;
        $item->save();

        return $item;
    }

    public function deployTemplate(InfraTemplate $template): InfraTemplate
    {
        if (is_string($template->dokploy_ref) && $template->dokploy_ref !== '') {
            return $template;
        }

        $result = $this->driver->deployCompose([
            'name' => $template->slug,
            'app_name' => Str::slug($template->slug),
            'compose' => $template->compose,
            'environment_id' => config('dockhost.dokploy.environment_id'),
        ]);

        $externalId = $result['external_id'] ?? null;
        $template->dokploy_ref = is_string($externalId) && $externalId !== ''
            ? $externalId
            : 'local_'.Str::slug($template->slug);
        $template->save();

        return $template;
    }

    private function engine(string $image): string
    {
        $image = strtolower($image);

        return match (true) {
            str_contains($image, 'postgres') => 'postgres',
            str_contains($image, 'mongo') => 'mongo',
            str_contains($image, 'mysql') => 'mysql',
            default => 'mariadb',
        };
    }

    private function composeFor(ServiceCatalogItem $item, string $password): string
    {
        $secret = str_replace(["\n", '"'], '', $password);
        $image = $item->image;

        if ($item->kind === 'object-storage' || str_contains(strtolower($image), 'minio')) {
            return <<<YAML
services:
  minio:
    image: {$image}
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

        if ($item->kind === 'sftp' || str_contains(strtolower($image), 'sftp')) {
            return <<<YAML
services:
  sftp:
    image: {$image}
    command: "dockhost:{$secret}:1001"
    volumes:
      - sftp:/home/dockhost/files
volumes:
  sftp:
YAML;
        }

        return <<<YAML
services:
  app:
    image: {$image}
YAML;
    }
}
