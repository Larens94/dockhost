<?php

namespace Tests\Feature;

use App\Models\Pool;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PoolCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_pool_admin_password_is_encrypted_and_hidden_from_the_form(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create();

        $this->actingAs($user)
            ->post(route('pools.store'), [
                'name' => 'db-secure',
                'kind' => 'database',
                'engine' => 'mariadb',
                'server_id' => $server->id,
                'capacity' => 20,
                'host' => '10.1.0.8',
                'port' => 3306,
                'mode' => 'shared',
                'admin_username' => 'root',
                'admin_password' => 's3cret-admin',
                'admin_database' => 'mysql',
            ])
            ->assertRedirect(route('pools.index'));

        $pool = Pool::query()->where('name', 'db-secure')->firstOrFail();
        $stored = (string) DB::table('pools')->where('id', $pool->id)->value('credentials');

        $this->assertSame('s3cret-admin', $pool->adminConnection()['admin_password']);
        $this->assertStringNotContainsString('s3cret-admin', $stored);
        $this->assertArrayNotHasKey('admin_password', $pool->meta ?? []);

        $this->actingAs($user)
            ->get(route('pools.edit', $pool))
            ->assertOk()
            ->assertDontSee('s3cret-admin');

        $this->actingAs($user)
            ->patch(route('pools.update', $pool), [
                'name' => 'db-secure',
                'kind' => 'database',
                'engine' => 'mariadb',
                'server_id' => $server->id,
                'capacity' => 20,
                'host' => '10.1.0.8',
                'port' => 3306,
                'mode' => 'shared',
                'admin_username' => 'root',
                'admin_password' => '',
                'admin_database' => 'mysql',
            ])
            ->assertRedirect(route('pools.index'));

        $this->assertSame('s3cret-admin', $pool->fresh()->adminConnection()['admin_password']);
    }

    public function test_operator_can_add_a_server(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('servers.store'), [
                'name' => 'db-1',
                'ip' => '10.2.0.4',
                'role' => 'database',
                'status' => 'online',
            ])
            ->assertRedirect(route('servers.index'));

        $this->assertDatabaseHas('servers', [
            'name' => 'db-1',
            'ip' => '10.2.0.4',
            'role' => 'database',
        ]);
    }
}
