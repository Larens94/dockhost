<?php

namespace Tests\Feature;

use App\Contracts\RuntimeAdmin;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PoolLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SiteOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_git_repository_is_sent_before_deploy_and_refresh_marks_the_site_active(): void
    {
        config([
            'dockhost.dokploy.url' => 'https://dokploy.test',
            'dockhost.dokploy.api_key' => 'test-key',
            'dockhost.dokploy.environment_id' => 'env_123',
        ]);

        $remoteStatus = 'idle';

        Http::fake(function ($request) use (&$remoteStatus) {
            if (str_contains($request->url(), 'application.one')) {
                return Http::response([
                    'applicationStatus' => $remoteStatus,
                    'environmentId' => 'env_123',
                    'environment' => [
                        'environmentId' => 'env_123',
                        'projectId' => 'proj_1',
                    ],
                ], 200);
            }

            return Http::response(['applicationId' => 'app_live'], 200);
        });

        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, [
                'domain' => 'git.example.test',
                'repository' => 'git@github.com:acme/shop.git',
                'git_branch' => 'develop',
            ]))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'git.example.test')->firstOrFail();

        $this->assertSame('provisioning', $site->status);
        $this->assertTrue($site->usage_held);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dokploy.test/api/application.saveGitProvider'
                && $request['customGitUrl'] === 'https://github.com/acme/shop.git'
                && $request['customGitBranch'] === 'develop'
                && $request['customGitBuildPath'] === '/'
                && $request['watchPaths'] === [];
        });

        $remoteStatus = 'done';

        $this->actingAs($world['user'])
            ->post(route('sites.refresh', $site))
            ->assertRedirect();

        $site->refresh();
        $this->assertSame('active', $site->status);
        $this->assertSame('proj_1', $site->meta['dokploy_project_id']);

        $this->actingAs($world['user'])
            ->get(route('sites.toolkit', $site))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'dokployLinks.application',
                'https://dokploy.test/dashboard/project/proj_1/environment/env_123/services/application/app_live',
            ));
    }

    public function test_dedicated_database_requires_the_entitlement_and_calls_dokploy(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->from(route('wizard.create'))
            ->post(route('wizard.store'), $this->payload($world, [
                'domain' => 'shared-only.example.test',
                'database_mode' => 'dedicated',
            ]))
            ->assertSessionHasErrors('database_mode');

        $world['plan']->update([
            'entitlements' => [
                'sftp' => true,
                'cache' => true,
                'dedicated_database' => true,
            ],
        ]);

        config([
            'dockhost.dokploy.url' => 'https://dokploy.test',
            'dockhost.dokploy.api_key' => 'test-key',
            'dockhost.dokploy.environment_id' => 'env_123',
        ]);

        Http::fake([
            'https://dokploy.test/api/*' => Http::response([
                'applicationId' => 'app_db',
                'applicationStatus' => 'done',
                'mariadbId' => 'mdb_1',
            ], 200),
        ]);

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, [
                'domain' => 'agency.example.test',
                'database_mode' => 'dedicated',
            ]))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'agency.example.test')->firstOrFail();

        $this->assertSame('dedicated', $site->options['database_mode']);
        $this->assertSame('provisioned', $site->databaseAccount->status);
        $this->assertSame('mdb_1', $site->databaseAccount->dokploy_ref);

        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/mariadb.create'
            && $request['databaseName'] === $site->databaseAccount->schema_name);
    }

    public function test_sftp_user_is_created_only_when_the_runtime_can_manage_the_pool(): void
    {
        $world = $this->world();
        $fake = new class implements RuntimeAdmin
        {
            public int $calls = 0;

            public function canManage(Pool $pool): bool
            {
                return true;
            }

            public function createSftpUser(Pool $pool, string $username, string $password, string $chroot): void
            {
                $this->calls++;
            }
        };
        $this->app->instance(RuntimeAdmin::class, $fake);

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'files.example.test']))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'files.example.test')->firstOrFail();

        $this->assertSame('active', $site->status);
        $this->assertSame('provisioned', $site->sftpAccount->status);
        $this->assertSame(1, $fake->calls);
        $this->assertSame('sftp'.$site->id, $site->sftpAccount->username);
    }

    public function test_sftp_stays_reserved_when_ssh_materialization_fails(): void
    {
        $world = $this->world();
        $this->app->instance(RuntimeAdmin::class, new class implements RuntimeAdmin
        {
            public function canManage(Pool $pool): bool
            {
                return true;
            }

            public function createSftpUser(Pool $pool, string $username, string $password, string $chroot): void
            {
                throw new \RuntimeException('ssh unavailable');
            }
        });

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'quiet.example.test']))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'quiet.example.test')->firstOrFail();

        $this->assertSame('active', $site->status);
        $this->assertSame('reserved', $site->sftpAccount->status);
    }

    public function test_suspending_a_client_stops_existing_sites_and_keeps_the_slot(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'hold.example.test']));

        $site = Site::query()->where('domain', 'hold.example.test')->firstOrFail();
        $this->assertSame('active', $site->status);

        $this->actingAs($world['user'])
            ->patch(route('clients.update', $world['client']), [
                'name' => $world['client']->name,
                'email' => $world['client']->email,
                'status' => 'suspended',
            ])
            ->assertRedirect();

        $site->refresh();
        $this->assertSame('suspended', $site->status);
        $this->assertTrue($site->usage_held);
        $this->assertSame(1, $world['runtime']->fresh()->usage);

        $this->actingAs($world['user'])
            ->patch(route('clients.update', $world['client']), [
                'name' => $world['client']->name,
                'email' => $world['client']->email,
                'status' => 'active',
            ]);

        $this->assertSame('active', $site->fresh()->status);
    }

    public function test_retry_reruns_a_failed_site_without_rotating_the_database_password(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'retry.example.test']));

        $site = Site::query()->where('domain', 'retry.example.test')->firstOrFail();
        $password = $site->databaseAccount->password;
        app(PoolLedger::class)->release($site);
        $site->status = 'failed';
        $site->last_error = 'boom';
        $site->save();

        $other = Pool::factory()->create(['name' => 'db-b', 'kind' => 'database']);

        $this->actingAs($world['user'])
            ->post(route('sites.retry', $site), [
                'database_pool_id' => $other->id,
                'storage_pool_id' => $world['storage']->id,
            ])
            ->assertRedirect();

        $site->refresh();
        $this->assertSame('active', $site->status);
        $this->assertTrue($site->usage_held);
        $this->assertSame($password, $site->databaseAccount->password);
        $this->assertSame($other->id, $site->databaseAccount->pool_id);
        $this->assertSame(1, $other->fresh()->usage);
        $this->assertSame(0, $world['database']->fresh()->usage);
    }

    public function test_alias_domain_is_recorded(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'alias.example.test']));

        $site = Site::query()->where('domain', 'alias.example.test')->firstOrFail();

        $this->actingAs($world['user'])
            ->post(route('sites.domains.store', $site), ['host' => 'www.alias.example.test'])
            ->assertRedirect();

        $this->assertDatabaseHas('site_domains', [
            'site_id' => $site->id,
            'host' => 'www.alias.example.test',
            'primary' => false,
        ]);
    }

    /**
     * @return array{user: User, runtime: Pool, database: Pool, storage: Pool, recipe: Recipe, plan: Plan, client: Client}
     */
    private function world(): array
    {
        $user = User::factory()->create();
        $runtime = Pool::factory()->create([
            'name' => 'app-web',
            'kind' => 'runtime',
            'engine' => 'php-fpm',
            'runtime_version' => '8.3',
            'meta' => null,
        ]);
        $database = Pool::factory()->create(['name' => 'db-a', 'kind' => 'database']);
        $storage = Pool::factory()->create([
            'name' => 'fs-1',
            'kind' => 'storage',
            'engine' => 'volume',
        ]);
        $recipe = Recipe::factory()->create();
        $plan = Plan::factory()->create(['site_quota' => 5]);
        $client = Client::factory()->create();
        Subscription::factory()->create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
        ]);

        return compact('user', 'runtime', 'database', 'storage', 'recipe', 'plan', 'client');
    }

    /**
     * @param  array<string, mixed>  $world
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $world, array $overrides = []): array
    {
        return array_merge([
            'client_id' => $world['client']->id,
            'domain' => 'shop.example.test',
            'recipe_id' => $world['recipe']->id,
            'wants_database' => 1,
            'database_pool_id' => $world['database']->id,
            'wants_storage' => 1,
            'storage_pool_id' => $world['storage']->id,
            'wants_sftp' => 1,
            'wants_cache' => 0,
            'cache_pool_id' => null,
        ], $overrides);
    }
}
