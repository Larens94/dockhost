<?php

// SecretsForExistingStackTest.php — updateStack must not invent MYSQL_* on existing compose.
//
// exports: SecretsForExistingStackTest
// used_by: none (PHPUnit entry)
// rules:   Empty panel passwords + empty compose env ⇒ ValidationException stack, no compose.update.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_mysql_heal | Refuse secret regen on live stack
// message:

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecretsForExistingStackTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_update_stack_refuses_to_invent_mysql_secrets_when_compose_exists(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'env' => "MYSQL_ROOT_PASSWORD=\nMYSQL_APP_PASSWORD=\n",
            ]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'mysql_root_password' => '',
            'mysql_admin_password' => '',
        ]);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->put(route('infrastructures.update', $infrastructure), [
                'enabled_services' => ['mariadb', 'postgres', 'sftp'],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('stack');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.update'
            || $request->url() === 'https://dokploy.test/api/compose.deploy');
    }
}
