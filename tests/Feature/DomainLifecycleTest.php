<?php


// DomainLifecycleTest.php — DomainLifecycleTest module.
//
// exports: DomainLifecycleTest | DomainLifecycleTest::test_authenticated_user_renames_a_domain(): void | DomainLifecycleTest::test_authenticated_user_deletes_a_domain_and_its_dokploy_app(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\SftpUser;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_renames_a_domain(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'old.acme.test']);

        $this->actingAs($user)
            ->put(route('domains.update', $domain), [
                'fqdn' => 'shop.acme.test',
            ])
            ->assertRedirect(route('domains.show', $domain));

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'fqdn' => 'shop.acme.test',
        ]);
    }

    public function test_authenticated_user_deletes_a_domain_and_its_dokploy_app(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/application.delete' => Http::response(['ok' => true]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Name' => '/infra1-sftp-1', 'Id' => 'sftpcontainerid', 'State' => 'running'],
            ]),
            'https://dokploy.test/api/docker.restartContainer' => Http::response(['ok' => true]),
            'http://*-sftp-sync:8787/*' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $infrastructure = $this->panelInfrastructure();
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.acme.test',
        ]);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-shop',
        ]);
        SftpUser::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
        ]);

        $subscriptionId = $domain->subscription_id;

        $this->actingAs($user)
            ->delete(route('domains.destroy', $domain))
            ->assertRedirect(route('subscriptions.show', $subscriptionId));

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
        $this->assertDatabaseMissing('dokploy_applications', ['dokploy_application_id' => 'app-shop']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.delete'
            && $request['applicationId'] === 'app-shop');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://infra1-sftp-sync:8787/sync');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/docker.restartContainer'
            && $request['containerId'] === 'sftpcontainerid');
    }
}
