<?php


// DestroyInfrastructureTest.php — DestroyInfrastructureTest module.
//
// exports: DestroyInfrastructureTest | DestroyInfrastructureTest::test_authenticated_user_deletes_infrastructure_without_domains(): void | DestroyInfrastructureTest::test_delete_purges_leftover_dokploy_projects_with_the_same_slug(): void | DestroyInfrastructureTest::test_delete_continues_when_compose_is_already_gone(): void | DestroyInfrastructureTest::test_delete_is_rejected_when_slug_does_not_match(): void | DestroyInfrastructureTest::test_delete_is_rejected_when_domains_are_attached(): void | DestroyInfrastructureTest::test_guests_cannot_delete_infrastructure(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DestroyInfrastructureTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_deletes_infrastructure_without_domains(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/compose.stop' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/project.remove' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_project_id' => 'proj-1',
            'dokploy_compose_id' => 'compose-1',
        ]);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->delete(route('infrastructures.destroy', $infrastructure), [
                'slug' => 'infra1',
            ])
            ->assertRedirect(route('infrastructures.index'));

        $this->assertDatabaseMissing('infrastructures', [
            'id' => $infrastructure->id,
            'slug' => 'infra1',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.stop'
            && $request['composeId'] === 'compose-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.delete'
            && $request['composeId'] === 'compose-1'
            && $request['deleteVolumes'] === true);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.remove'
            && $request['projectId'] === 'proj-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/settings.cleanUnusedVolumes');
    }

    public function test_delete_purges_leftover_dokploy_projects_with_the_same_slug(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/project.all' => Http::response([
                ['projectId' => 'proj-stale', 'name' => 'infra1'],
                ['projectId' => 'proj-other', 'name' => 'infra2'],
            ]),
            'https://dokploy.test/api/project.one*' => Http::response([
                'environments' => [
                    [
                        'compose' => [
                            ['composeId' => 'compose-stale'],
                        ],
                    ],
                ],
            ]),
            'https://dokploy.test/api/compose.stop' => Http::response(['ok' => true]),
            'https://dokploy.test/api/compose.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/project.remove' => Http::response(['ok' => true]),
            'https://dokploy.test/api/settings.cleanUnusedVolumes' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_project_id' => 'proj-1',
            'dokploy_compose_id' => 'compose-1',
        ]);

        $this->actingAs($user)
            ->delete(route('infrastructures.destroy', $infrastructure), [
                'slug' => 'infra1',
            ])
            ->assertRedirect(route('infrastructures.index'));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.delete'
            && $request['composeId'] === 'compose-stale'
            && $request['deleteVolumes'] === true);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/settings.cleanUnusedVolumes');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.remove'
            && $request['projectId'] === 'proj-stale');

        Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/project.remove'
            && $request['projectId'] === 'proj-other');
    }

    public function test_delete_continues_when_compose_is_already_gone(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$this->dokployProjectDiscoveryFakes(),
            'https://dokploy.test/api/compose.stop' => Http::response(['message' => 'Compose not found'], 404),
            'https://dokploy.test/api/compose.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/project.remove' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_project_id' => 'proj-1',
            'dokploy_compose_id' => 'compose-gone',
        ]);

        $this->actingAs($user)
            ->delete(route('infrastructures.destroy', $infrastructure), [
                'slug' => 'infra1',
            ])
            ->assertRedirect(route('infrastructures.index'));

        $this->assertDatabaseMissing('infrastructures', [
            'id' => $infrastructure->id,
        ]);
    }

    public function test_delete_is_rejected_when_slug_does_not_match(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_project_id' => 'proj-1',
        ]);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->delete(route('infrastructures.destroy', $infrastructure), [
                'slug' => 'infra2',
            ])
            ->assertRedirect(route('infrastructures.show', $infrastructure))
            ->assertSessionHasErrors('slug');

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'slug' => 'infra1',
        ]);

        Http::assertNothingSent();
    }

    public function test_delete_is_rejected_when_domains_are_attached(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_project_id' => 'proj-1',
        ]);
        Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => 'infra1',
        ]);

        $this->actingAs($user)
            ->from(route('infrastructures.show', $infrastructure))
            ->delete(route('infrastructures.destroy', $infrastructure), [
                'slug' => 'infra1',
            ])
            ->assertRedirect(route('infrastructures.show', $infrastructure))
            ->assertSessionHasErrors(['slug' => 'Ci sono domini collegati']);

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'slug' => 'infra1',
        ]);

        Http::assertNothingSent();
    }

    public function test_guests_cannot_delete_infrastructure(): void
    {
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
        ]);

        $this->delete(route('infrastructures.destroy', $infrastructure), [
            'slug' => 'infra1',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
        ]);
    }
}
