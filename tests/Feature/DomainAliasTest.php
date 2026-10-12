<?php

// DomainAliasTest.php — Alias and subdomain hostnames on a primary Domain.
//
// exports: DomainAliasTest
// used_by: none
// rules:   web_service calls Dokploy domain.create on the same application; delete calls domain.delete.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Feature tests for aliases
// message:

namespace Tests\Feature;

use App\Enums\DomainAliasKind;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\DomainAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DomainAliasTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_user_creates_alias_with_web_service_on_same_application(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'dom-alias-1']),
        ]);

        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'asefecommerce.space']);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-asef',
        ]);

        $this->actingAs($user)
            ->post(route('domains.aliases.store', $domain), [
                'kind' => 'alias',
                'fqdn' => 'www.demoeforge1.it',
                'web_service' => true,
                'redirect_301' => false,
            ])
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'hosts']));

        $this->assertDatabaseHas('domain_aliases', [
            'domain_id' => $domain->id,
            'fqdn' => 'demoeforge1.it',
            'kind' => DomainAliasKind::Alias->value,
            'web_service' => true,
            'redirect_301' => false,
            'dokploy_domain_id' => 'dom-alias-1',
        ]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.create'
            && $request['host'] === 'demoeforge1.it'
            && $request['applicationId'] === 'app-asef'
            && $request['domainType'] === 'application');
    }

    public function test_authenticated_user_creates_subdomain_under_primary_fqdn(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/domain.create' => Http::response(['domainId' => 'dom-sub-1']),
        ]);

        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'asefecommerce.space']);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-asef',
        ]);

        $this->actingAs($user)
            ->post(route('domains.aliases.store', $domain), [
                'kind' => 'subdomain',
                'subdomain' => 'App',
                'web_service' => true,
            ])
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'hosts']));

        $this->assertDatabaseHas('domain_aliases', [
            'domain_id' => $domain->id,
            'fqdn' => 'app.asefecommerce.space',
            'kind' => DomainAliasKind::Subdomain->value,
            'dokploy_domain_id' => 'dom-sub-1',
        ]);
    }

    public function test_alias_without_web_service_skips_dokploy(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'primary.test']);

        $this->actingAs($user)
            ->post(route('domains.aliases.store', $domain), [
                'kind' => 'alias',
                'fqdn' => 'alias.test',
                'web_service' => false,
            ])
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'hosts']));

        $this->assertDatabaseHas('domain_aliases', [
            'domain_id' => $domain->id,
            'fqdn' => 'alias.test',
            'web_service' => false,
            'dokploy_domain_id' => null,
        ]);

        Http::assertNothingSent();
    }

    public function test_web_service_requires_dokploy_application(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'primary.test']);

        $this->actingAs($user)
            ->from(route('domains.show', $domain))
            ->post(route('domains.aliases.store', $domain), [
                'kind' => 'alias',
                'fqdn' => 'alias.test',
                'web_service' => true,
            ])
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHasErrors('web_service');

        $this->assertDatabaseCount('domain_aliases', 0);
    }

    public function test_duplicate_fqdn_is_rejected(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'primary.test']);
        DomainAlias::factory()->create([
            'domain_id' => $domain->id,
            'fqdn' => 'taken.test',
        ]);

        $this->actingAs($user)
            ->from(route('domains.show', $domain))
            ->post(route('domains.aliases.store', $domain), [
                'kind' => 'alias',
                'fqdn' => 'taken.test',
                'web_service' => false,
            ])
            ->assertRedirect(route('domains.show', $domain))
            ->assertSessionHasErrors('fqdn');
    }

    public function test_authenticated_user_deletes_alias_and_dokploy_domain(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/domain.delete' => Http::response(['ok' => true]),
        ]);

        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'primary.test']);
        DokployApplication::factory()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => 'app-primary',
        ]);
        $alias = DomainAlias::factory()->create([
            'domain_id' => $domain->id,
            'fqdn' => 'alias.test',
            'dokploy_domain_id' => 'dom-alias-del',
        ]);

        $this->actingAs($user)
            ->delete(route('domains.aliases.destroy', [$domain, $alias]))
            ->assertRedirect(route('domains.show', ['domain' => $domain, 'tab' => 'hosts']));

        $this->assertDatabaseMissing('domain_aliases', ['id' => $alias->id]);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/domain.delete'
            && $request['domainId'] === 'dom-alias-del');
    }

    public function test_domain_show_includes_aliases_relation(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create(['fqdn' => 'primary.test']);
        DomainAlias::factory()->create([
            'domain_id' => $domain->id,
            'fqdn' => 'alias.test',
            'kind' => DomainAliasKind::Alias,
        ]);

        $this->actingAs($user)
            ->get(route('domains.show', ['domain' => $domain, 'tab' => 'hosts']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Domains/Show')
                ->has('domain.aliases', 1)
                ->where('domain.aliases.0.fqdn', 'alias.test'));
    }
}
