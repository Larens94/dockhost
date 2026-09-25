<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\InfraTemplate;
use App\Models\Plan;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Server;
use App\Models\ServiceCatalogItem;
use App\Models\Site;
use App\Models\SiteDatabase;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EnvironmentBuilder;
use App\Services\PoolLedger;
use App\Support\ApplicationToolkit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@dockhost.local'],
            [
                'name' => 'DockHost Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => 'superadmin',
            ]
        );

        $manager = Server::query()->updateOrCreate(
            ['name' => 'swarm-manager-1'],
            [
                'ip' => '10.0.0.10',
                'role' => 'manager',
                'status' => 'online',
                'dokploy_server_id' => 'srv_manager_1',
            ]
        );

        $worker = Server::query()->updateOrCreate(
            ['name' => 'swarm-worker-1'],
            [
                'ip' => '10.0.0.11',
                'role' => 'worker',
                'status' => 'online',
                'dokploy_server_id' => 'srv_worker_1',
            ]
        );

        $dbPool = Pool::query()->updateOrCreate(
            ['name' => 'db-a'],
            [
                'kind' => 'database',
                'engine' => 'mariadb',
                'server_id' => $manager->id,
                'capacity' => 120,
                'usage' => 0,
                'dokploy_ref' => 'compose:mariadb-shared',
                'meta' => ['host' => '10.0.0.10', 'port' => 3306, 'mode' => 'shared'],
            ]
        );

        $fsPool = Pool::query()->updateOrCreate(
            ['name' => 'fs-1'],
            [
                'kind' => 'storage',
                'engine' => 'volume',
                'server_id' => $worker->id,
                'capacity' => 200,
                'usage' => 0,
                'dokploy_ref' => 'compose:storage-sftp',
                'meta' => ['host' => '10.0.0.11', 'mode' => 'shared'],
            ]
        );

        $runtimePool = Pool::query()->updateOrCreate(
            ['name' => 'app-web'],
            [
                'kind' => 'runtime',
                'engine' => 'php-fpm',
                'runtime_version' => '8.3',
                'server_id' => $worker->id,
                'capacity' => 80,
                'usage' => 0,
                'dokploy_ref' => 'swarm:app-web',
            ]
        );

        Pool::query()->updateOrCreate(
            ['name' => 'cache-a'],
            [
                'kind' => 'cache',
                'engine' => 'redis',
                'server_id' => $manager->id,
                'capacity' => 50,
                'usage' => 0,
                'dokploy_ref' => 'compose:redis-shared',
                'meta' => ['host' => '10.0.0.10', 'port' => 6379],
            ]
        );

        foreach ([
            ['MariaDB 11', 'database', 'mariadb:11', 'shared', 'official'],
            ['PostgreSQL 16', 'database', 'postgres:16', 'shared', 'official'],
            ['Redis 7', 'cache', 'redis:7', 'shared', 'official'],
            ['MinIO', 'object-storage', 'minio/minio:latest', 'shared', 'beta'],
            ['SFTP (chroot)', 'sftp', 'atmoz/sftp:latest', 'shared', 'official'],
            ['MongoDB 7', 'database', 'mongo:7', 'dedicated', 'community'],
        ] as [$name, $kind, $image, $mode, $support]) {
            ServiceCatalogItem::query()->updateOrCreate(
                ['name' => $name],
                compact('kind', 'image', 'mode', 'support')
            );
        }

        InfraTemplate::query()->updateOrCreate(
            ['slug' => 'shared-sql-storage'],
            [
                'name' => 'Shared SQL + Storage + SFTP',
                'summary' => 'Dense hosting baseline: MariaDB, volumes, SFTP daemon',
                'version' => '1.0.0',
                'services' => ['mariadb', 'volumes', 'sftp'],
                'compose' => <<<'YAML'
services:
  mariadb:
    image: mariadb:11
    environment:
      MARIADB_ROOT_PASSWORD: change-me
      MARIADB_DATABASE: app
    volumes:
      - mariadb:/var/lib/mysql
  sftp:
    image: atmoz/sftp:latest
    command: "dockhost:change-me:1001"
    volumes:
      - files:/home/dockhost/files
volumes:
  mariadb:
  files:
YAML,
            ]
        );

        InfraTemplate::query()->updateOrCreate(
            ['slug' => 'postgres-redis'],
            [
                'name' => 'Postgres + Redis',
                'summary' => 'Shared Postgres and Redis for modern apps',
                'version' => '1.0.0',
                'services' => ['postgres', 'redis'],
                'compose' => <<<'YAML'
services:
  postgres:
    image: postgres:16
    environment:
      POSTGRES_PASSWORD: change-me
      POSTGRES_DB: app
    volumes:
      - postgres:/var/lib/postgresql/data
  redis:
    image: redis:7
    command: ["redis-server", "--requirepass", "change-me"]
    volumes:
      - redis:/data
volumes:
  postgres:
  redis:
YAML,
            ]
        );

        $laravel = Recipe::query()->updateOrCreate(
            ['slug' => 'laravel-app'],
            [
                'name' => 'Laravel App',
                'stack' => 'laravel',
                'summary' => 'First installable application — PHP/Laravel on shared pools',
                'version' => '1.0.0',
                'status' => 'stable',
                'enabled' => true,
                'sort' => 10,
                'requires' => ['database', 'storage', 'runtime'],
                'toolkit' => ApplicationToolkit::forStack('laravel'),
                'steps' => [
                    ['op' => 'ensure_database'],
                    ['op' => 'ensure_storage_path'],
                    ['op' => 'ensure_sftp_user'],
                    ['op' => 'deploy_application'],
                    ['op' => 'attach_domain_ssl'],
                ],
            ]
        );

        Recipe::query()->updateOrCreate(
            ['slug' => 'wordpress'],
            [
                'name' => 'WordPress',
                'stack' => 'wordpress',
                'summary' => 'Planned recipe — same pools, different runtime steps',
                'version' => '0.1.0',
                'status' => 'beta',
                'enabled' => true,
                'sort' => 20,
                'requires' => ['database', 'storage', 'runtime'],
                'toolkit' => ApplicationToolkit::forStack('wordpress'),
                'steps' => [
                    ['op' => 'ensure_database'],
                    ['op' => 'ensure_storage_path'],
                    ['op' => 'deploy_application'],
                    ['op' => 'attach_domain_ssl'],
                ],
            ]
        );

        Recipe::query()->updateOrCreate(
            ['slug' => 'node-api'],
            [
                'name' => 'Node API',
                'stack' => 'node',
                'summary' => 'Future recipe — Node runtime on shared/dedicated pools',
                'version' => '0.1.0',
                'status' => 'draft',
                'enabled' => true,
                'sort' => 30,
                'requires' => ['database', 'cache', 'runtime'],
                'toolkit' => ApplicationToolkit::forStack('node'),
                'steps' => [
                    ['op' => 'ensure_database'],
                    ['op' => 'deploy_application'],
                    ['op' => 'attach_domain_ssl'],
                ],
            ]
        );

        Recipe::query()->updateOrCreate(
            ['slug' => 'static-site'],
            [
                'name' => 'Static Site',
                'stack' => 'static',
                'summary' => 'Nginx/static assets — minimal pools',
                'version' => '0.1.0',
                'status' => 'beta',
                'enabled' => true,
                'sort' => 40,
                'requires' => ['storage', 'runtime'],
                'toolkit' => ApplicationToolkit::forStack('static'),
                'steps' => [
                    ['op' => 'ensure_storage_path'],
                    ['op' => 'deploy_application'],
                    ['op' => 'attach_domain_ssl'],
                ],
            ]
        );

        $starter = Plan::query()->updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'stripe_price_id' => 'price_starter_stub',
                'amount_cents' => 1900,
                'currency' => 'eur',
                'interval' => 'month',
                'site_quota' => 3,
                'features' => ['3 sites', 'Shared DB', 'SFTP', 'Email support'],
                'entitlements' => ['sftp' => true, 'cache' => false, 'dedicated_database' => false],
                'active' => true,
            ]
        );

        $business = Plan::query()->updateOrCreate(
            ['slug' => 'business'],
            [
                'name' => 'Business',
                'stripe_price_id' => 'price_business_stub',
                'amount_cents' => 4900,
                'currency' => 'eur',
                'interval' => 'month',
                'site_quota' => 15,
                'features' => ['15 sites', 'Priority pools', 'SFTP', 'Cache', 'Chat support'],
                'entitlements' => ['sftp' => true, 'cache' => true, 'dedicated_database' => false],
                'active' => true,
            ]
        );

        Plan::query()->updateOrCreate(
            ['slug' => 'agency'],
            [
                'name' => 'Agency',
                'stripe_price_id' => 'price_agency_stub',
                'amount_cents' => 9900,
                'currency' => 'eur',
                'interval' => 'month',
                'site_quota' => 50,
                'features' => ['50 sites', 'Multi-pool', 'SFTP', 'Cache', 'Dedicated DB', 'SLA'],
                'entitlements' => ['sftp' => true, 'cache' => true, 'dedicated_database' => true],
                'active' => true,
            ]
        );

        $acme = Client::query()->updateOrCreate(
            ['email' => 'ops@acme.test'],
            [
                'name' => 'Acme Studio',
                'company' => 'Acme Srl',
                'status' => 'active',
                'billing_email' => 'ops@acme.test',
                'billing_status' => 'active',
            ]
        );

        $beta = Client::query()->updateOrCreate(
            ['email' => 'hello@beta.test'],
            [
                'name' => 'Beta Retail',
                'company' => 'Beta Retail',
                'status' => 'active',
                'billing_email' => 'hello@beta.test',
                'billing_status' => 'active',
            ]
        );

        Client::query()->updateOrCreate(
            ['email' => 'ops@gamma.test'],
            [
                'name' => 'Gamma Paused',
                'company' => 'Gamma',
                'status' => 'suspended',
                'billing_email' => 'ops@gamma.test',
                'billing_status' => 'past_due',
            ]
        );

        Subscription::query()->updateOrCreate(
            ['client_id' => $acme->id, 'plan_id' => $business->id],
            [
                'status' => 'active',
                'stripe_subscription_id' => 'sub_stub_acme',
                'current_period_end' => now()->addMonth(),
                'meta' => ['mode' => 'stub'],
            ]
        );

        Subscription::query()->updateOrCreate(
            ['client_id' => $beta->id, 'plan_id' => $starter->id],
            [
                'status' => 'active',
                'stripe_subscription_id' => 'sub_stub_beta',
                'current_period_end' => now()->addMonth(),
                'meta' => ['mode' => 'stub'],
            ]
        );

        $shop = Site::query()->updateOrCreate(
            ['domain' => 'shop.acme.test'],
            [
                'client_id' => $acme->id,
                'recipe_id' => $laravel->id,
                'status' => 'active',
                'repository' => 'git@github.com:acme/shop.git',
                'usage_held' => true,
                'options' => [
                    'wants_database' => true,
                    'database_pool_id' => $dbPool->id,
                    'wants_storage' => true,
                    'storage_pool_id' => $fsPool->id,
                    'wants_sftp' => true,
                    'wants_cache' => false,
                    'cache_pool_id' => null,
                    'runtime_pool_id' => $runtimePool->id,
                    'storage_path' => '/var/sites/shop',
                ],
                'pool_ids' => [$dbPool->id, $fsPool->id, $runtimePool->id],
                'toolkit_state' => [
                    'schedule_enabled' => true,
                    'queue_enabled' => true,
                    'maintenance' => false,
                    'queue_jobs' => 1,
                    'last_commit' => [
                        'hash' => 'e0baa10c88c826930c8612ed89531f',
                        'author' => 'Acme Dev',
                        'date' => '2026-09-10',
                        'message' => 'Initial Laravel site on DockHost pools',
                    ],
                ],
            ]
        );

        $portal = Site::query()->updateOrCreate(
            ['domain' => 'portal.beta.test'],
            [
                'client_id' => $beta->id,
                'recipe_id' => $laravel->id,
                'status' => 'pending',
                'repository' => 'git@github.com:beta/portal.git',
                'pool_ids' => [$dbPool->id, $fsPool->id, $runtimePool->id],
            ]
        );

        $ledger = app(PoolLedger::class);

        foreach ([$shop, $portal] as $site) {
            $ledger->attach($site, [
                'database' => $dbPool->id,
                'storage' => $fsPool->id,
                'runtime' => $runtimePool->id,
            ]);
        }

        SiteDatabase::query()->updateOrCreate(
            ['site_id' => $shop->id],
            [
                'pool_id' => $dbPool->id,
                'engine' => 'mariadb',
                'schema_name' => 'db_shop',
                'username' => 'u_shop',
                'password' => 'seedpassword1',
                'host' => '10.0.0.10',
                'port' => 3306,
                'status' => 'reserved',
            ]
        );

        app(EnvironmentBuilder::class)->persist($shop->fresh());
    }
}
