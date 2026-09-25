<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\Site;
use Illuminate\Support\Str;

class EnvironmentBuilder
{
    /**
     * Secret keys masked in the toolkit preview.
     *
     * @var list<string>
     */
    private array $secretKeys = [
        'APP_KEY',
        'DB_PASSWORD',
        'REDIS_PASSWORD',
        'SFTP_PASSWORD',
        'AWS_SECRET_ACCESS_KEY',
        'MINIO_ROOT_PASSWORD',
    ];

    /**
     * @return array<string, string>
     */
    public function build(Site $site): array
    {
        $site->loadMissing(['databaseAccount', 'sftpAccount']);
        $options = $site->options ?? [];
        $existing = $site->environment ?? [];
        $state = $site->toolkit_state ?? [];

        $env = [
            'APP_NAME' => (string) Str::of($site->domain)->before('.')->replace(['-', '_'], ' ')->title()->replace(' ', ''),
            'APP_ENV' => 'production',
            'APP_KEY' => $existing['APP_KEY'] ?? 'base64:'.base64_encode(random_bytes(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://'.$site->domain,
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            'DOCKHOST_SCHEDULE' => ! empty($state['schedule_enabled']) ? 'true' : 'false',
            'DOCKHOST_QUEUE' => ! empty($state['queue_enabled']) ? 'true' : 'false',
            'DOCKHOST_MAINTENANCE' => ! empty($state['maintenance']) ? 'true' : 'false',
        ];

        $secrets = $site->service_secrets ?? [];

        if ($database = $site->databaseAccount) {
            $engine = strtolower($database->engine);
            $postgres = str_starts_with($engine, 'postgres');
            $mongo = str_starts_with($engine, 'mongo');
            $env['DB_CONNECTION'] = $mongo ? 'mongodb' : ($postgres ? 'pgsql' : 'mysql');
            $env['DB_HOST'] = $database->host ?: '127.0.0.1';
            $env['DB_PORT'] = (string) ($database->port ?: ($mongo ? 27017 : ($postgres ? 5432 : 3306)));
            $env['DB_DATABASE'] = $database->schema_name;
            $env['DB_USERNAME'] = $database->username;
            $env['DB_PASSWORD'] = (string) $database->password;
        }

        if (! empty($options['cache_pool_id'])) {
            $cache = Pool::query()->find($options['cache_pool_id']);
            $env['REDIS_HOST'] = $secrets['redis_host'] ?? $cache?->meta['host'] ?? '127.0.0.1';
            $env['REDIS_PORT'] = (string) ($secrets['redis_port'] ?? $cache?->meta['port'] ?? 6379);
            $env['CACHE_STORE'] = 'redis';
            $env['QUEUE_CONNECTION'] = 'redis';

            if (! empty($secrets['redis_password'])) {
                $env['REDIS_PASSWORD'] = (string) $secrets['redis_password'];
            }
        }

        if (! empty($options['wants_object_storage'])) {
            $env['FILESYSTEM_DISK'] = 's3';
            $env['AWS_ACCESS_KEY_ID'] = (string) ($secrets['minio_access_key'] ?? 'dockhost');
            $env['AWS_SECRET_ACCESS_KEY'] = (string) ($secrets['minio_secret_key'] ?? '');
            $env['AWS_DEFAULT_REGION'] = 'us-east-1';
            $env['AWS_BUCKET'] = 'site-'.$site->id;
            $env['AWS_ENDPOINT'] = (string) ($secrets['minio_endpoint'] ?? '');
            $env['AWS_USE_PATH_STYLE_ENDPOINT'] = 'true';
        }

        if (! empty($options['runtime_pool_id'])) {
            $runtime = Pool::query()->find($options['runtime_pool_id']);
            if ($runtime?->runtime_version) {
                $env['PHP_VERSION'] = $runtime->runtime_version;
            }
        }

        if ($site->sftpAccount) {
            $env['SFTP_USERNAME'] = $site->sftpAccount->username;
            $env['SFTP_PASSWORD'] = (string) $site->sftpAccount->password;
        }

        if ($site->repository) {
            $env['DOCKHOST_REPOSITORY'] = $site->repository;
        }

        if (! empty($options['storage_path'])) {
            $env['DOCKHOST_STORAGE_PATH'] = $options['storage_path'];
        }

        return $env;
    }

    /**
     * @return array<string, string>
     */
    public function persist(Site $site): array
    {
        $env = $this->build($site);
        $site->environment = $env;
        $site->save();

        return $env;
    }

    /**
     * @param  array<string, string>  $env
     */
    public function render(array $env): string
    {
        return collect($env)
            ->map(fn (string $value, string $key) => $key.'='.$this->quote($value))
            ->implode("\n");
    }

    /**
     * @param  array<string, string>  $env
     */
    public function masked(array $env): string
    {
        $masked = $env;

        foreach ($this->secretKeys as $key) {
            if (! empty($masked[$key])) {
                $masked[$key] = '••••••••';
            }
        }

        return $this->render($masked);
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"/', $value)) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }
}
