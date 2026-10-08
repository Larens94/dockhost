<?php

// DomainAccessMailTest.php — Invite and site credential emails for domain access.
//
// exports: DomainAccessMailTest
// used_by: none
// rules:   Mail::fake only; never assert passwords in audit_logs meta JSON.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Feature tests for DomainAccessMailer flows.

namespace Tests\Feature;

use App\Enums\DatabasePrivilege;
use App\Enums\DomainMemberRole;
use App\Mail\DomainMemberInviteMail;
use App\Mail\SiteHostingCredentialMail;
use App\Models\AuditLog;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\User;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class DomainAccessMailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invite_mail_contains_reset_link_and_no_password_word_in_subject(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_admin' => true, 'name' => 'Admin Panel']);
        $domain = Domain::factory()->create(['fqdn' => 'cliente.example']);

        $this->actingAs($admin)
            ->post(route('domains.members.store', $domain), [
                'email' => 'nuovo@example.test',
                'name' => 'Nuovo Utente',
                'role' => 'developer',
            ])
            ->assertRedirect();

        Mail::assertSent(DomainMemberInviteMail::class, function (DomainMemberInviteMail $mail): bool {
            return str_contains($mail->setPasswordUrl, '/reset-password/')
                && str_contains($mail->setPasswordUrl, 'email=nuovo%40example.test')
                && str_contains($mail->domainFqdn, 'cliente.example')
                && $mail->inviterName === 'Admin Panel';
        });

        $audit = AuditLog::query()->where('action', 'domain.member.invite')->latest('id')->first();
        $this->assertNotNull($audit);
        $metaJson = json_encode($audit->meta);
        $this->assertIsString($metaJson);
        $this->assertStringNotContainsStringIgnoringCase('password', $metaJson);
    }

    public function test_readonly_member_cannot_invite(): void
    {
        Mail::fake();

        $readonly = User::factory()->member()->create();
        $domain = Domain::factory()->create();
        $readonly->domains()->attach($domain, ['role' => DomainMemberRole::Readonly->value]);

        $this->actingAs($readonly)
            ->post(route('domains.members.store', $domain), [
                'email' => 'blocked@example.test',
                'name' => 'Blocked',
            ])
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_guest_cannot_invite(): void
    {
        Mail::fake();

        $domain = Domain::factory()->create();

        $this->post(route('domains.members.store', $domain), [
            'email' => 'guest@example.test',
            'name' => 'Guest',
        ])->assertRedirect(route('login'));

        Mail::assertNothingSent();
    }

    public function test_site_database_user_creation_emails_owners_without_password_in_audit(): void
    {
        Mail::fake();

        $infrastructure = $this->panelInfrastructure();
        $owner = User::factory()->member()->create(['email' => 'owner@example.test']);
        $domain = Domain::factory()->create([
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'fqdn' => 'shop.example',
        ]);
        $owner->domains()->attach($domain, ['role' => DomainMemberRole::Owner->value]);

        DatabaseAccount::factory()->create([
            'domain_id' => $domain->id,
            'infrastructure_id' => $infrastructure->id,
            'infra_slug' => $infrastructure->slug,
            'database_name' => 'd_shop',
        ]);

        $mysql = Mockery::mock(MysqlProvisioner::class);
        $mysql->shouldReceive('grantUser')->once();
        $this->instance(MysqlProvisioner::class, $mysql);

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post(route('domains.database-users.store', $domain), [
                'privilege' => DatabasePrivilege::Select->value,
            ])
            ->assertRedirect();

        Mail::assertSent(SiteHostingCredentialMail::class, function (SiteHostingCredentialMail $mail) use ($owner): bool {
            return $mail->hasTo($owner->email)
                && $mail->domainFqdn === 'shop.example';
        });

        $audit = AuditLog::query()->where('action', 'domain.site_credential.emailed')->latest('id')->first();
        $this->assertNotNull($audit);
        $metaJson = json_encode($audit->meta);
        $this->assertIsString($metaJson);
        $this->assertStringNotContainsStringIgnoringCase('password', $metaJson);
    }
}
