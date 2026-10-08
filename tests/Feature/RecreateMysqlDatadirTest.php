<?php


// RecreateMysqlDatadirTest.php — RecreateMysqlDatadirTest module.
//
// exports: RecreateMysqlDatadirTest | RecreateMysqlDatadirTest::test_authenticated_user_recreates_only_the_mariadb_volume(): void | RecreateMysqlDatadirTest::test_reset_requires_matching_slug(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecreateMysqlDatadirTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_recreates_only_the_mariadb_volume(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployComposeStatusFakes(),
            'https://dokploy.test/api/compose.stop' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.update' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.saveEnvironment' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            'https://dokploy.test/api/domain.create' => Http::response(['ok' => true]),
            'https://dokploy.test/api/settings.cleanUnusedVolumes' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $infrastructure = $this->panelInfrastructure([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'mariadb_volume_generation' => 0,
            'mariadb_volume_name' => null,
        ]);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->post(route('infrastructures.mysql.reset', $infrastructure), [
                'slug' => 'infra1',
            ])
            ->assertRedirect(route('infrastructures.show', $infrastructure));

        $infrastructure->refresh();

        $this->assertSame(1, $infrastructure->mariadb_volume_generation);
        $this->assertSame('infra1_mariadb_v1', $infrastructure->mariadb_volume_name);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.stop'
            && $request['composeId'] === 'compose-1');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://dokploy.test/api/compose.update'
                && str_contains((string) $request['composeFile'], 'name: infra1_mariadb_v1');
        });

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/settings.cleanUnusedVolumes');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.delete');
        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.remove');
    }

    public function test_reset_requires_matching_slug(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $infrastructure = $this->panelInfrastructure(['slug' => 'infra1']);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->post(route('infrastructures.mysql.reset', $infrastructure), [
                'slug' => 'infra2',
            ])
            ->assertSessionHasErrors('slug');
    }
}
