<?php

namespace Tests\Feature;

use App\Models\InfraTemplate;
use App\Models\ServiceCatalogItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogDeployTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_services_and_compose_templates_are_created_on_dokploy(): void
    {
        config([
            'dockhost.dokploy.url' => 'https://dokploy.test',
            'dockhost.dokploy.api_key' => 'test-key',
            'dockhost.dokploy.environment_id' => 'env_123',
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'redis.create')) {
                return Http::response(['redisId' => 'redis_cat'], 200);
            }

            if (str_contains($url, 'mysql.create')) {
                return Http::response(['mysqlId' => 'mysql_cat'], 200);
            }

            if (str_contains($url, 'compose.create')) {
                return Http::response(['composeId' => 'cmp_cat'], 200);
            }

            return Http::response(['ok' => true], 200);
        });

        $user = User::factory()->create();
        $redis = ServiceCatalogItem::query()->create([
            'name' => 'Redis 7',
            'kind' => 'cache',
            'image' => 'redis:7',
            'mode' => 'shared',
            'support' => 'official',
        ]);
        $mysql = ServiceCatalogItem::query()->create([
            'name' => 'MySQL 8',
            'kind' => 'database',
            'image' => 'mysql:8',
            'mode' => 'dedicated',
            'support' => 'official',
        ]);
        $minio = ServiceCatalogItem::query()->create([
            'name' => 'MinIO',
            'kind' => 'object-storage',
            'image' => 'minio/minio:latest',
            'mode' => 'shared',
            'support' => 'beta',
        ]);
        $template = InfraTemplate::query()->create([
            'name' => 'Postgres + Redis',
            'slug' => 'postgres-redis',
            'summary' => 'Shared Postgres and Redis',
            'version' => '1.0.0',
            'services' => ['postgres', 'redis'],
            'compose' => "services:\n  redis:\n    image: redis:7\n",
        ]);

        $this->actingAs($user)->post(route('services.deploy', $redis))->assertRedirect();
        $this->actingAs($user)->post(route('services.deploy', $mysql))->assertRedirect();
        $this->actingAs($user)->post(route('services.deploy', $minio))->assertRedirect();
        $this->actingAs($user)->post(route('templates.deploy', $template))->assertRedirect();

        $this->assertSame('redis_cat', $redis->fresh()->meta['dokploy_ref']);
        $this->assertSame('mysql_cat', $mysql->fresh()->meta['dokploy_ref']);
        $this->assertSame('cmp_cat', $minio->fresh()->meta['dokploy_ref']);
        $this->assertSame('cmp_cat', $template->fresh()->dokploy_ref);

        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/redis.create'
            && $request['dockerImage'] === 'redis:7');
        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/mysql.create'
            && $request['databaseName'] === 'app');
        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/compose.update'
            && str_contains((string) $request['composeFile'], 'minio/minio:latest'));
        Http::assertSent(fn ($request) => $request->url() === 'https://dokploy.test/api/compose.update'
            && str_contains((string) $request['composeFile'], 'redis:7'));

        $this->actingAs($user)->post(route('services.deploy', $redis))->assertRedirect();
        Http::assertSentCount(8);
    }
}
