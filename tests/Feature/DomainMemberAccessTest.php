<?php

// DomainMemberAccessTest.php — Domain-scoped panel access for non-admin members.
//
// exports: DomainMemberAccessTest
// used_by: none
// rules:   Members get 404 on other domains and admin routes; admins retain full access.
//          Invite must not require password; readonly blocked on mutate POST; owner can invite.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | Feature tests for domain_user pivot.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | IAM roles, invite email, password reset route.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Assert DomainMemberInviteMail instead of notification.

namespace Tests\Feature;

use App\Enums\DomainMemberRole;
use App\Mail\DomainMemberInviteMail;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DomainMemberAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_member_cannot_see_other_domain(): void
    {
        $member = User::factory()->member()->create();
        $allowed = Domain::factory()->create(['fqdn' => 'allowed.test']);
        $other = Domain::factory()->create(['fqdn' => 'secret.test']);

        $member->domains()->attach($allowed, ['role' => DomainMemberRole::Developer->value]);

        $this->actingAs($member)
            ->get(route('domains.show', $other))
            ->assertNotFound();
    }

    public function test_member_sees_own_domain(): void
    {
        $member = User::factory()->member()->create();
        $domain = Domain::factory()->create(['fqdn' => 'mine.test']);
        $member->domains()->attach($domain, ['role' => DomainMemberRole::Developer->value]);

        $this->actingAs($member)
            ->get(route('domains.show', $domain))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Show')
                ->where('domain.fqdn', 'mine.test')
                ->where('canOpenDokploy', false)
                ->where('canMutateHosting', true));
    }

    public function test_member_domains_index_lists_only_assigned_hostings(): void
    {
        $member = User::factory()->member()->create();
        $first = Domain::factory()->create(['fqdn' => 'a.test']);
        $second = Domain::factory()->create(['fqdn' => 'b.test']);
        $member->domains()->attach([
            $first->id => ['role' => DomainMemberRole::Developer->value],
            $second->id => ['role' => DomainMemberRole::Developer->value],
        ]);
        Domain::factory()->create(['fqdn' => 'hidden.test']);

        $this->actingAs($member)
            ->get(route('domains.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Domains/Index')
                ->has('domains', 2));
    }

    public function test_admin_sees_all_domains_on_index(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        Domain::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('domains.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('domains', 3));
    }

    public function test_member_cannot_access_infrastructures(): void
    {
        $member = User::factory()->member()->create();
        Infrastructure::factory()->create();

        $this->actingAs($member)
            ->get(route('infrastructures.index'))
            ->assertNotFound();
    }

    public function test_member_cannot_destroy_infrastructure(): void
    {
        $member = User::factory()->member()->create();
        $infra = Infrastructure::factory()->create(['slug' => 'infra1']);

        $this->actingAs($member)
            ->delete(route('infrastructures.destroy', $infra), ['slug' => 'infra1'])
            ->assertNotFound();
    }

    public function test_admin_can_grant_domain_access_without_password(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $domain = Domain::factory()->create();

        $this->actingAs($admin)
            ->post(route('domains.members.store', $domain), [
                'email' => 'habibur@example.test',
                'name' => 'Habibur',
                'role' => 'owner',
            ])
            ->assertRedirect();

        $member = User::query()->where('email', 'habibur@example.test')->first();
        $this->assertNotNull($member);
        $this->assertFalse($member->isAdmin());
        $this->assertTrue($member->canAccessDomain($domain));
        $this->assertSame(
            DomainMemberRole::Owner,
            $member->domainMemberRole($domain),
        );

        Mail::assertSent(DomainMemberInviteMail::class, fn (DomainMemberInviteMail $mail): bool => $mail->hasTo('habibur@example.test'));
    }

    public function test_readonly_member_cannot_create_database_user(): void
    {
        $member = User::factory()->member()->create();
        $domain = Domain::factory()->create();
        $member->domains()->attach($domain, ['role' => DomainMemberRole::Readonly->value]);

        $this->actingAs($member)
            ->post(route('domains.database-users.store', $domain), [
                'privilege' => 'all',
            ])
            ->assertForbidden();
    }

    public function test_domain_owner_can_invite_member(): void
    {
        Mail::fake();

        $owner = User::factory()->member()->create();
        $domain = Domain::factory()->create(['fqdn' => 'reteinazione.test']);
        $owner->domains()->attach($domain, ['role' => DomainMemberRole::Owner->value]);

        $this->actingAs($owner)
            ->post(route('domains.members.store', $domain), [
                'email' => 'dev@example.test',
                'name' => 'Dev',
                'role' => 'developer',
            ])
            ->assertRedirect();

        $invited = User::query()->where('email', 'dev@example.test')->first();
        $this->assertNotNull($invited);
        $this->assertTrue($invited->canAccessDomain($domain));

        Mail::assertSent(DomainMemberInviteMail::class);
    }

    public function test_developer_cannot_manage_domain_members(): void
    {
        $developer = User::factory()->member()->create();
        $domain = Domain::factory()->create();
        $developer->domains()->attach($domain, ['role' => DomainMemberRole::Developer->value]);

        $this->actingAs($developer)
            ->post(route('domains.members.store', $domain), [
                'email' => 'other@example.test',
                'name' => 'Other',
            ])
            ->assertForbidden();
    }

    public function test_forgot_password_route_exists(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }
}
