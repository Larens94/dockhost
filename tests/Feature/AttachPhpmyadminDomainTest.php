<?php


// AttachPhpmyadminDomainTest.php — AttachPhpmyadminDomainTest module.
//
// exports: AttachPhpmyadminDomainTest | AttachPhpmyadminDomainTest::test_existing_infrastructure_attaches_wildcard_phpmyadmin_domain(): void | AttachPhpmyadminDomainTest::test_guests_cannot_attach_phpmyadmin_domain(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttachPhpmyadminDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_infrastructure_attaches_wildcard_phpmyadmin_domain(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'pma-1']),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'phpmyadmin_domain' => null,
        ]);

        $this->actingAs($user)
            ->post(route('infrastructures.phpmyadmin.store', $infrastructure))
            ->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'phpmyadmin_domain' => 'pma-infra1.cloud.silicoreautomation.com',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pma-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'phpmyadmin'
            && $request['composeId'] === 'compose-1'
            && $request['domainType'] === 'compose'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.deploy'
            && $request['composeId'] === 'compose-1');
    }

    public function test_guests_cannot_attach_phpmyadmin_domain(): void
    {
        $infrastructure = Infrastructure::factory()->create();

        $this->post(route('infrastructures.phpmyadmin.store', $infrastructure))
            ->assertRedirect(route('login'));
    }
}
