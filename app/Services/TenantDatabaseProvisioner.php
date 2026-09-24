<?php

namespace App\Services;

use App\Contracts\DatabaseAdmin;
use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Site;
use App\Models\SiteDatabase;
use Illuminate\Support\Str;
use RuntimeException;

class TenantDatabaseProvisioner
{
    public function __construct(
        private DatabaseAdmin $admin,
        private InfrastructureDriver $driver,
    ) {}

    public function ensure(Site $site, Pool $pool): SiteDatabase
    {
        $engine = strtolower((string) ($pool->engine ?: 'mariadb'));
        $account = SiteDatabase::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'pool_id' => $pool->id,
                'engine' => $engine,
                'schema_name' => $this->identifier('db', $site),
                'username' => $this->identifier('u', $site),
                'password' => Str::password(24, symbols: false),
                'host' => $pool->meta['host'] ?? null,
                'port' => (int) ($pool->meta['port'] ?? (str_starts_with($engine, 'postgres') ? 5432 : 3306)),
                'status' => 'reserved',
            ]
        );

        $mode = $pool->meta['mode'] ?? 'shared';

        if ($mode === 'dedicated') {
            $remote = $this->driver->createDatabase([
                'name' => 'db-'.$site->id,
                'app_name' => 'db-'.$site->id,
                'engine' => $engine,
                'database' => $account->schema_name,
                'username' => $account->username,
                'password' => $account->password,
                'environment_id' => $pool->meta['dokploy_environment_id'] ?? config('dockhost.dokploy.environment_id'),
                'image' => $pool->meta['image'] ?? null,
            ]);

            $account->dokploy_ref = $remote['external_id'] ?? null;
            $account->status = ($remote['status'] ?? 'reserved') === 'provisioned' ? 'provisioned' : 'reserved';
            $account->save();

            return $account;
        }

        if (! empty($pool->meta['admin_username']) && ! empty($pool->meta['host'])) {
            $this->admin->exec($pool, $this->statements($account, create: true));
            $account->status = 'provisioned';
            $account->save();
        }

        return $account;
    }

    public function drop(SiteDatabase $account): void
    {
        $pool = $account->pool;

        if (! $pool || $account->status !== 'provisioned') {
            return;
        }

        if (($pool->meta['mode'] ?? 'shared') === 'dedicated') {
            return;
        }

        if (empty($pool->meta['admin_username']) || empty($pool->meta['host'])) {
            return;
        }

        $this->admin->exec($pool, $this->statements($account, create: false));
    }

    /**
     * @return list<string>
     */
    private function statements(SiteDatabase $account, bool $create): array
    {
        $schema = $this->assertIdent($account->schema_name);
        $user = $this->assertIdent($account->username);
        $password = str_replace("'", "''", (string) $account->password);
        $postgres = str_starts_with(strtolower($account->engine), 'postgres');

        if ($postgres) {
            return $create
                ? [
                    "CREATE DATABASE \"{$schema}\"",
                    "CREATE USER \"{$user}\" WITH PASSWORD '{$password}'",
                    "GRANT ALL PRIVILEGES ON DATABASE \"{$schema}\" TO \"{$user}\"",
                ]
                : [
                    "DROP DATABASE IF EXISTS \"{$schema}\"",
                    "DROP USER IF EXISTS \"{$user}\"",
                ];
        }

        return $create
            ? [
                "CREATE DATABASE IF NOT EXISTS `{$schema}`",
                "CREATE USER IF NOT EXISTS '{$user}'@'%' IDENTIFIED BY '{$password}'",
                "GRANT ALL PRIVILEGES ON `{$schema}`.* TO '{$user}'@'%'",
                'FLUSH PRIVILEGES',
            ]
            : [
                "DROP DATABASE IF EXISTS `{$schema}`",
                "DROP USER IF EXISTS '{$user}'@'%'",
                'FLUSH PRIVILEGES',
            ];
    }

    private function identifier(string $prefix, Site $site): string
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $site->domain));
        $slug = trim($slug, '_');

        return $this->assertIdent(substr($prefix.$site->id.'_'.$slug, 0, 32));
    }

    private function assertIdent(string $value): string
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,31}$/', $value)) {
            throw new RuntimeException('Unsafe database identifier.');
        }

        return $value;
    }
}
