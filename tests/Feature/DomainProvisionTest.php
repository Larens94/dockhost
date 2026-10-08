<?php

// DomainProvisionTest.php — DomainProvisionTest module.
//
// exports: DomainProvisionTest | DomainProvisionTest::test_wizard_provisions_domain_database_storage_and_sftp(): void | DomainProvisionTest::test_wizard_uses_hosts_from_created_infrastructure(): void | DomainProvisionTest::test_wizard_provisions_postgres_on_shared_infra(): void | DomainProvisionTest::test_wizard_attaches_dokploy_application_when_requested(): void | DomainProvisionTest::test_php_stack_wires_infra_db_without_laravel_keys(): void | DomainProvisionTest::test_static_stack_wires_infra_without_app_key(): void | DomainProvisionTest::test_laravel_env_includes_redis_and_minio_when_infra_has_them(): void | DomainProvisionTest::test_laravel_attach_deletes_dokploy_app_when_save_environment_fails(): void | DomainProvisionTest::test_guests_cannot_provision_a_domain(): void | DomainProvisionTest::test_mysql_access_denied_returns_validation_error_and_rolls_back_domain(): void
// used_by: none
// rules:   Laravel attach env uses SESSION_DRIVER=database and SESSION_DOMAIN of the site fqdn. PHP stack omits SESSION_DRIVER.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_session_cookie | Laravel provision asserts database session cookie
// message:

namespace Tests\Feature;

use App\Enums\DatabaseEngine;
use App\Models\Customer;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Infra\ComposeMysqlCredentialAligner;
use App\Services\Infra\MysqlProvisioner;
use App\Services\Infra\PostgresProvisioner;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use PDO;
use PDOException;
use Tests\TestCase;

class DomainProvisionTest extends TestCase
{
    public function test_wizard_provisions_domain_database_storage_and_sftp(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'https://dokploy.test/*' => Http::response(['ok' => true]),
            'http://infra1-sftp-sync:8787/sync' => Http::response(['ok' => true]),
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')
            ->once()
            ->withArgs(function (string $database, string $username, string $password): bool {
                return $database !== '' && $username !== '' && $password !== '';
            });
        $this->instance(MysqlProvisioner::class, $mysql);

        $infrastructure = $this->panelInfrastructure();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Corp'])->id,
        ]);

        $response = $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra1',
            'stack' => 'none',
        ]);

        $domain = Domain::query()->where('fqdn', 'shop.acme.test')->firstOrFail();
        $response->assertRedirect(route('domains.show', $domain));
        $response->assertSessionHas('revealed_credential', function (array $payload): bool {
            $kinds = array_column($payload, 'kind');

            return in_array('database', $kinds, true)
                && in_array('sftp', $kinds, true)
                && collect($payload)->every(fn (array $item): bool => filled($item['password'] ?? null));
        });

        $this->assertDatabaseHas('domains', [
            'customer_id' => $subscription->customer_id,
            'subscription_id' => $subscription->id,
            'fqdn' => 'shop.acme.test',
            'infra_slug' => 'infra1',
            'infrastructure_id' => $infrastructure->id,
            'stack' => 'none',
        ]);

        $this->assertDatabaseHas('database_accounts', [
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra1',
            'host' => 'infra1-mariadb',
            'port' => 3306,
        ]);

        $storagePath = rtrim((string) config('infra.storage_root'), '/').'/acme-corp/shop.acme.test';

        $this->assertDatabaseHas('storage_shares', [
            'path' => $storagePath,
        ]);

        $this->assertDatabaseHas('sftp_users', [
            'home_path' => $storagePath,
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://infra1-sftp-sync:8787/sync'
            && collect($request['users'] ?? [])->contains(fn (array $user): bool => ($user['username'] ?? null) === 'sftp_shop_acme_test'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/docker.restartContainer'
            && $request['containerId'] === 'sftpcontainerid');
    }

    public function test_wizard_uses_hosts_from_created_infrastructure(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->panelInfrastructure([
            'slug' => 'infra2',
            'mysql_host' => 'infra2-mariadb',
            'storage_root' => sys_get_temp_dir().'/silicore-host-infra2',
            'sftp_users_file' => sys_get_temp_dir().'/silicore-host-infra2/.sftp/users.conf',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Corp'])->id,
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra2',
            'stack' => 'none',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'shop.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('database_accounts', [
            'infra_slug' => 'infra2',
            'host' => 'infra2-mariadb',
        ]);
    }

    public function test_wizard_provisions_postgres_on_shared_infra(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldNotReceive('provision');
        $this->instance(MysqlProvisioner::class, $mysql);

        $postgres = Mockery::mock(PostgresProvisioner::class);
        $postgres->shouldReceive('provision')->once();
        $this->instance(PostgresProvisioner::class, $postgres);

        $this->panelInfrastructure();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Corp'])->id,
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'api.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Postgres->value,
            'infra_slug' => 'infra1',
            'stack' => 'none',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'api.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('database_accounts', [
            'engine' => DatabaseEngine::Postgres->value,
            'host' => 'infra1-postgres',
            'port' => 5432,
        ]);
    }

    public function test_wizard_attaches_dokploy_application_when_requested(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $infrastructure = $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Corp'])->id,
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra1',
            'stack' => 'laravel',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'shop.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('dokploy_applications', [
            'dokploy_application_id' => 'app-1',
            'dokploy_environment_id' => 'env-infra-1',
        ]);
        $this->assertDatabaseHas('domains', [
            'fqdn' => 'shop.acme.test',
            'stack' => 'laravel',
        ]);
        $this->assertNull(
            DokployApplication::query()->where('dokploy_application_id', 'app-1')->value('git_url'),
        );

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.create'
            && $request['environmentId'] === 'env-infra-1'
            && $request['projectId'] === 'proj-infra-1');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains((string) $request['env'], 'LOG_CHANNEL=stderr')
            && str_contains((string) $request['env'], 'APP_URL=https://shop.acme.test')
            && str_contains((string) $request['env'], 'ASSET_URL=https://shop.acme.test')
            && str_contains((string) $request['env'], 'TRUSTED_PROXIES=*')
            && str_contains((string) $request['env'], 'APP_ENV=production')
            && str_contains((string) $request['env'], 'APP_DEBUG=false')
            && str_contains((string) $request['env'], 'SESSION_DRIVER=database')
            && str_contains((string) $request['env'], 'SESSION_SECURE_COOKIE=true')
            && str_contains((string) $request['env'], 'SESSION_DOMAIN=shop.acme.test')
            && str_contains((string) $request['env'], 'CACHE_STORE=file')
            && str_contains((string) $request['env'], 'DOKHOSTS_STORAGE_PATH=')
            && str_contains((string) $request['env'], 'DOKHOSTS_INFRA_SLUG=infra1')
            && str_contains((string) $request['env'], 'DB_HOST=infra1-mariadb')
            && preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', (string) $request['env']) === 1
            && ! str_contains((string) $request['env'], 'B2_')
            && ! str_contains((string) $request['env'], 'STRIPE_')
            && ! str_contains((string) $request['env'], 'REVERB_')
            && $request['buildArgs'] === ''
            && $request['buildSecrets'] === ''
            && $request['createEnvFile'] === false);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/mounts.create'
            && $request['volumeName'] === 'infra1_data'
            && $request['mountPath'] === '/data'
            && $request['serviceId'] === 'app-1');

        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveGitProvider');
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.deploy');

        Http::assertSentCount(7);
        $this->assertSame('env-infra-1', $infrastructure->dokploy_environment_id);
    }

    public function test_php_stack_wires_infra_db_without_laravel_keys(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach();

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('provision')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'customer_id' => Customer::factory()->create(['name' => 'Acme Corp'])->id,
        ]);

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'php.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
            'infra_slug' => 'infra1',
            'stack' => 'php',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'php.acme.test')->firstOrFail()));

        $this->assertDatabaseHas('domains', [
            'fqdn' => 'php.acme.test',
            'stack' => 'php',
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.create'
            && $request['environmentId'] === 'env-infra-1'
            && $request['projectId'] === 'proj-infra-1');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains((string) $request['env'], 'APP_URL=https://php.acme.test')
            && str_contains((string) $request['env'], 'DOKHOSTS_STORAGE_PATH=')
            && str_contains((string) $request['env'], 'DOKHOSTS_INFRA_SLUG=infra1')
            && str_contains((string) $request['env'], 'DB_HOST=infra1-mariadb')
            && str_contains((string) $request['env'], 'DB_DATABASE=')
            && ! str_contains((string) $request['env'], 'APP_KEY=')
            && ! str_contains((string) $request['env'], 'SESSION_DRIVER=')
            && ! str_contains((string) $request['env'], 'CACHE_STORE=')
            && ! str_contains((string) $request['env'], 'APP_ENV='));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/mounts.create'
            && $request['volumeName'] === 'infra1_data');
        Http::assertNotSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveGitProvider');
    }

    public function test_static_stack_wires_infra_without_app_key(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach();

        $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'static.acme.test',
            'infra_slug' => 'infra1',
            'stack' => 'static',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'static.acme.test')->firstOrFail()));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains((string) $request['env'], 'APP_URL=https://static.acme.test')
            && str_contains((string) $request['env'], 'DOKHOSTS_STORAGE_PATH=')
            && str_contains((string) $request['env'], 'DOKHOSTS_INFRA_SLUG=infra1')
            && ! str_contains((string) $request['env'], 'APP_KEY=')
            && ! str_contains((string) $request['env'], 'DB_HOST='));
    }

    public function test_laravel_env_includes_redis_and_minio_when_infra_has_them(): void
    {
        Http::preventStrayRequests();
        $this->fakeLaravelDokployAttach();

        $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
            'enabled_services' => [
                'mariadb',
                'mysql-grants',
                'postgres',
                'sftp-users-init',
                'sftp',
                'phpmyadmin',
                'redis',
                'minio',
            ],
            'redis_host' => 'infra1-redis',
            'minio_host' => 'infra1-minio',
            'minio_root_user' => 'miniokey',
            'minio_root_password' => 'miniosecret',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'cache.acme.test',
            'stack' => 'laravel',
            'infra_slug' => 'infra1',
        ])->assertRedirect(route('domains.show', Domain::query()->where('fqdn', 'cache.acme.test')->firstOrFail()));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://dokploy.test/api/application.saveEnvironment'
            && str_contains((string) $request['env'], 'REDIS_HOST=infra1-redis')
            && str_contains((string) $request['env'], 'AWS_ENDPOINT=http://infra1-minio:9000')
            && str_contains((string) $request['env'], 'AWS_ACCESS_KEY_ID=miniokey')
            && str_contains((string) $request['env'], 'CACHE_STORE=file')
            && ! str_contains((string) $request['env'], 'FILESYSTEM_DISK=s3')
            && ! str_contains((string) $request['env'], 'B2_')
            && ! str_contains((string) $request['env'], 'STRIPE_')
            && ! str_contains((string) $request['env'], 'REVERB_'));
    }

    public function test_laravel_attach_deletes_dokploy_app_when_save_environment_fails(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.create' => Http::response(['applicationId' => 'app-orphan']),
            'https://dokploy.test/api/application.saveEnvironment' => Http::response([
                'message' => 'Input validation failed',
                'code' => 'BAD_REQUEST',
            ], 400),
            'https://dokploy.test/api/application.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
        ]);

        $this->panelInfrastructure([
            'dokploy_environment_id' => 'env-infra-1',
            'dokploy_project_id' => 'proj-infra-1',
        ]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)->from(route('subscriptions.domains.create', $subscription))->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'infra_slug' => 'infra1',
            'stack' => 'laravel',
        ])->assertRedirect(route('subscriptions.domains.create', $subscription))
            ->assertSessionHasErrors('stack');

        $this->assertDatabaseMissing('domains', ['fqdn' => 'shop.acme.test']);
        $this->assertDatabaseCount('dokploy_applications', 0);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.delete'
            && $request['applicationId'] === 'app-orphan');
    }

    public function test_guests_cannot_provision_a_domain(): void
    {
        $subscription = Subscription::factory()->create();

        $this->post(route('subscriptions.domains.store', $subscription), [
            'fqdn' => 'shop.acme.test',
            'create_database' => true,
            'engine' => DatabaseEngine::Mysql->value,
        ])->assertRedirect(route('login'));
    }

    public function test_mysql_access_denied_returns_validation_error_and_rolls_back_domain(): void
    {
        Http::preventStrayRequests();
        $this->fakeSftpSync();

        $aligner = Mockery::mock(ComposeMysqlCredentialAligner::class);
        $aligner->shouldReceive('align')->andReturn(false);

        $this->instance(
            MysqlProvisioner::class,
            new MysqlProvisioner(
                fn (): PDO => Mockery::mock(PDO::class),
                $aligner,
                function (): PDO {
                    throw new PDOException("SQLSTATE[HY000] [1045] Access denied for user 'infra'@'10.0.1.96' (using password: YES)");
                },
            ),
        );

        $this->panelInfrastructure();
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $this->actingAs($user)
            ->from(route('subscriptions.domains.create', $subscription))
            ->post(route('subscriptions.domains.store', $subscription), [
                'fqdn' => 'test.vibesbridge.com',
                'create_database' => true,
                'engine' => DatabaseEngine::Mysql->value,
                'infra_slug' => 'infra1',
                'stack' => 'none',
            ])
            ->assertRedirect(route('subscriptions.domains.create', $subscription))
            ->assertSessionHasErrors('create_database');

        $this->assertDatabaseMissing('domains', ['fqdn' => 'test.vibesbridge.com']);
    }
}
