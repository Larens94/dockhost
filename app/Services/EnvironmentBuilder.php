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

        if ($database = $site->databaseAccount) {
            $postgres = str_starts_with(strtolower($database->engine), 'postgres');
            $env['DB_CONNECTION'] = $postgres ? 'pgsql' : 'mysql';
            $env['DB_HOST'] = $database->host ?: '127.0.0.1';
            $env['DB_PORT'] = (string) ($database->port ?: ($postgres ? 5432 : 3306));
            $env['DB_DATABASE'] = $database->schema_name;
            $env['DB_USERNAME'] = $database->username;
            $env['DB_PASSWORD'] = (string) $database->password;
        }

        if (! empty($options['cache_pool_id'])) {
            $cache = Pool::query()->find($options['cache_pool_id']);
            $env['REDIS_HOST'] = $cache?->meta['host'] ?? '127.0.0.1';
            $env['REDIS_PORT'] = (string) ($cache?->meta['port'] ?? 6379);
            $env['CACHE_STORE'] = 'redis';
            $env['QUEUE_CONNECTION'] = 'redis';
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
