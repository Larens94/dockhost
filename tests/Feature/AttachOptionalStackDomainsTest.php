<?php


// AttachOptionalStackDomainsTest.php — AttachOptionalStackDomainsTest module.
//
// exports: AttachOptionalStackDomainsTest | AttachOptionalStackDomainsTest::test_existing_infrastructure_attaches_pgadmin_and_minio_domains(): void | AttachOptionalStackDomainsTest::test_guests_cannot_attach_optional_domains(): void
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

class AttachOptionalStackDomainsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_existing_infrastructure_attaches_pgadmin_and_minio_domains(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'opt-1']),
            'https://dokploy.test/api/compose.deploy' => Http::response(['ok' => true]),
            ...$this->dokployComposeStatusFakes(),
        ]);

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'enabled_services' => [
                'mariadb',
                'mysql-grants',
                'postgres',
                'sftp-users-init',
                'sftp',
                'pgadmin',
                'minio',
            ],
            'pgadmin_domain' => null,
            'minio_domain' => null,
        ]);

        $this->actingAs($user)
            ->post(route('infrastructures.pgadmin.store', $infrastructure))
            ->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->actingAs($user)
            ->post(route('infrastructures.minio.store', $infrastructure))
            ->assertRedirect(route('infrastructures.show', $infrastructure));

        $this->assertDatabaseHas('infrastructures', [
            'id' => $infrastructure->id,
            'pgadmin_domain' => 'pga-infra1.cloud.silicoreautomation.com',
            'minio_domain' => 'minio-infra1.cloud.silicoreautomation.com',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'pga-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'pgadmin'
            && $request['path'] === '/'
            && $request['port'] === 80
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'minio-infra1.cloud.silicoreautomation.com'
            && $request['serviceName'] === 'minio'
            && $request['path'] === '/'
            && $request['port'] === 9001
            && $request['https'] === true
            && $request['certificateType'] === 'letsencrypt'
            && $request['domainType'] === 'compose'
            && $request['composeId'] === 'compose-1');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/compose.deploy'
            && $request['composeId'] === 'compose-1');
    }

    public function test_guests_cannot_attach_optional_domains(): void
    {
        $infrastructure = Infrastructure::factory()->create();

        $this->post(route('infrastructures.pgadmin.store', $infrastructure))
            ->assertRedirect(route('login'));
        $this->post(route('infrastructures.minio.store', $infrastructure))
            ->assertRedirect(route('login'));
    }
}
