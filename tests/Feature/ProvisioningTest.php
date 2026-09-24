<?php

namespace Tests\Feature;

use App\Contracts\DatabaseAdmin;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_reserves_a_database_sftp_account_and_environment(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'shop.example.test')->firstOrFail();

        $this->assertSame('active', $site->status);
        $this->assertTrue($site->usage_held);
        $this->assertNotNull($site->databaseAccount);
        $this->assertSame('reserved', $site->databaseAccount->status);
        $this->assertSame('mariadb', $site->databaseAccount->engine);
        $this->assertNotSame(
            $site->databaseAccount->password,
            DB::table('site_databases')->where('site_id', $site->id)->value('password')
        );
        $this->assertNotNull($site->sftpAccount);
        $this->assertSame('/var/sites/'.$site->id, $site->sftpAccount->chroot_path);
        $this->assertSame($site->databaseAccount->schema_name, $site->environment['DB_DATABASE']);
        $this->assertSame('8.3', $site->environment['PHP_VERSION']);
        $this->assertSame(1, $world['database']->fresh()->usage);
        $this->assertSame(1, $world['storage']->fresh()->usage);
        $this->assertSame(1, $world['runtime']->fresh()->usage);
        $this->assertStringStartsWith('local_', (string) $site->dokploy_app_id);
    }

    public function test_shared_pool_admin_connection_creates_the_schema(): void
    {
        $world = $this->world();
        $world['database']->update([
            'meta' => [
                'host' => '10.0.0.10',
                'port' => 3306,
                'mode' => 'shared',
                'admin_username' => 'root',
                'admin_password' => 'secret',
            ],
        ]);

        $fake = new class implements DatabaseAdmin
        {
            /** @var list<string> */
            public array $statements = [];

            public function exec(Pool $pool, array $statements): void
            {
                $this->statements = $statements;
            }
        };

        $this->app->instance(DatabaseAdmin::class, $fake);

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'sql.example.test']))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'sql.example.test')->firstOrFail();

        $this->assertSame('provisioned', $site->databaseAccount->status);
        $this->assertNotEmpty($fake->statements);
        $this->assertStringContainsString('CREATE DATABASE', $fake->statements[0]);
        $this->assertStringContainsString($site->databaseAccount->schema_name, $fake->statements[0]);
    }

    public function test_site_quota_blocks_another_site(): void
    {
        $world = $this->world();
        $world['plan']->update(['site_quota' => 1]);

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world))
            ->assertRedirect();

        $this->actingAs($world['user'])
            ->from(route('wizard.create'))
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'two.example.test']))
            ->assertRedirect(route('wizard.create'))
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseMissing('sites', ['domain' => 'two.example.test']);
    }

    public function test_suspended_client_cannot_provision(): void
    {
        $world = $this->world();
        $world['client']->update(['status' => 'suspended']);

        $this->actingAs($world['user'])
            ->from(route('wizard.create'))
            ->post(route('wizard.store'), $this->payload($world))
            ->assertSessionHasErrors('client_id');
    }

    public function test_recipe_requirements_are_enforced(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->from(route('wizard.create'))
            ->post(route('wizard.store'), $this->payload($world, [
                'wants_database' => 0,
                'database_pool_id' => null,
            ]))
            ->assertSessionHasErrors('wants_database');
    }

    public function test_deleting_a_site_releases_pool_capacity(): void
    {
        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world));

        $site = Site::query()->where('domain', 'shop.example.test')->firstOrFail();

        $this->actingAs($world['user'])
            ->delete(route('sites.destroy', $site))
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('site_databases', ['site_id' => $site->id]);
        $this->assertSame(0, $world['database']->fresh()->usage);
        $this->assertSame(0, $world['runtime']->fresh()->usage);
    }

    public function test_artisan_allowlist_rejects_destructive_commands(): void
    {
        $world = $this->world();
        $site = Site::factory()->create([
            'client_id' => $world['client']->id,
            'recipe_id' => $world['recipe']->id,
            'domain' => 'tools.example.test',
            'status' => 'active',
        ]);

        $this->actingAs($world['user'])
            ->from(route('sites.toolkit', $site))
            ->post(route('sites.artisan', $site), ['command' => 'migrate:fresh'])
            ->assertSessionHasErrors('command');

        $this->actingAs($world['user'])
            ->post(route('sites.artisan', $site), ['command' => 'about'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $history = $site->fresh()->toolkit_state['artisan_history'][0];
        $this->assertSame('about', $history['command']);
        $this->assertStringContainsString('Recorded only', $history['output']);
    }

    public function test_dokploy_application_is_created_when_configured(): void
    {
        config([
            'dockhost.dokploy.url' => 'https://dokploy.test',
            'dockhost.dokploy.api_key' => 'test-key',
            'dockhost.dokploy.environment_id' => 'env_123',
        ]);

        Http::fake([
            'https://dokploy.test/api/*' => Http::response(['applicationId' => 'app_live'], 200),
        ]);

        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'live.example.test']))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'live.example.test')->firstOrFail();

        $this->assertSame('provisioning', $site->status);
        $this->assertTrue($site->usage_held);
        $this->assertSame('app_live', $site->dokploy_app_id);
        $this->assertDatabaseHas('site_domains', [
            'site_id' => $site->id,
            'host' => 'live.example.test',
            'primary' => true,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://dokploy.test/api/application.create'
                && $request['environmentId'] === 'env_123'
                && $request->hasHeader('x-api-key', 'test-key');
        });

        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/domain.create');
    }

    public function test_missing_dokploy_environment_marks_the_site_failed_and_releases_capacity(): void
    {
        config([
            'dockhost.dokploy.url' => 'https://dokploy.test',
            'dockhost.dokploy.api_key' => 'test-key',
            'dockhost.dokploy.environment_id' => null,
        ]);

        Http::fake();

        $world = $this->world();

        $this->actingAs($world['user'])
            ->post(route('wizard.store'), $this->payload($world, ['domain' => 'broken.example.test']))
            ->assertRedirect();

        $site = Site::query()->where('domain', 'broken.example.test')->firstOrFail();

        $this->assertSame('failed', $site->status);
        $this->assertFalse($site->usage_held);
        $this->assertStringContainsString('DOKPLOY_ENVIRONMENT_ID', (string) $site->last_error);
        $this->assertSame(0, $world['database']->fresh()->usage);
    }

    /**
     * @return array{user: User, runtime: Pool, database: Pool, storage: Pool, cache: Pool, recipe: Recipe, plan: Plan, client: Client}
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
            'meta' => ['host' => '10.0.0.11', 'mode' => 'shared'],
        ]);
        Pool::factory()->create([
            'name' => 'cache-a',
            'kind' => 'cache',
            'engine' => 'redis',
            'meta' => ['host' => '10.0.0.10', 'port' => 6379],
        ]);
        $recipe = Recipe::factory()->create();
        $plan = Plan::factory()->create(['site_quota' => 2]);
        $client = Client::factory()->create();
        Subscription::factory()->create([
            'client_id' => $client->id,
            'plan_id' => $plan->id,
        ]);

        return compact('user', 'runtime', 'database', 'storage', 'recipe', 'plan', 'client');
    }

    /**
     * @param  array{user: User, runtime: Pool, database: Pool, storage: Pool, recipe: Recipe, plan: Plan, client: Client}  $world
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
