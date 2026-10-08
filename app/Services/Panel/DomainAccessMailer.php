<?php

// DomainAccessMailer.php — Send panel invite and site credential emails via panel SMTP.
//
// exports: DomainAccessMailer | sendMemberInvite | sendMemberAccessGranted | sendSiteCredentialToOwners
// used_by: app/Http/Controllers/DomainMemberController.php
//         app/Http/Controllers/DomainController.php
// rules:   mergeStoredIntoConfig before Mail::send. Never audit/log passwords or reset tokens.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Central mailer for Accessi + site creds.

namespace App\Services\Panel;

use App\Enums\DomainMemberRole;
use App\Mail\DomainMemberAccessGrantedMail;
use App\Mail\DomainMemberInviteMail;
use App\Mail\SiteHostingCredentialMail;
use App\Models\Domain;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

class DomainAccessMailer
{
    public function __construct(
        private PanelSmtpSettingsStore $smtpSettings,
        private AuditLogger $audit,
    ) {}

    public function sendMemberInvite(
        User $invitee,
        Domain $domain,
        User $inviter,
        DomainMemberRole $role,
    ): void {
        $this->smtpSettings->mergeStoredIntoConfig();

        $token = Password::broker()->createToken($invitee);
        $setPasswordUrl = URL::route('password.reset', [
            'token' => $token,
            'email' => $invitee->email,
        ]);

        Mail::to($invitee->email)->send(new DomainMemberInviteMail(
            inviteeName: $invitee->name,
            inviterName: $inviter->name,
            domainFqdn: $domain->fqdn,
            setPasswordUrl: $setPasswordUrl,
            roleLabel: $role->label(),
        ));

        $this->audit->record('domain.member.invite_email', $domain, [
            'fqdn' => $domain->fqdn,
            'recipient' => $invitee->email,
            'role' => $role->value,
            'new_user' => true,
        ]);
    }

    public function sendMemberAccessGranted(
        User $member,
        Domain $domain,
        User $inviter,
        DomainMemberRole $role,
    ): void {
        $this->smtpSettings->mergeStoredIntoConfig();

        $loginUrl = URL::route('login');

        Mail::to($member->email)->send(new DomainMemberAccessGrantedMail(
            memberName: $member->name,
            inviterName: $inviter->name,
            domainFqdn: $domain->fqdn,
            loginUrl: $loginUrl,
            roleLabel: $role->label(),
        ));

        $this->audit->record('domain.member.access_email', $domain, [
            'fqdn' => $domain->fqdn,
            'recipient' => $member->email,
            'role' => $role->value,
        ]);
    }

    /**
     * @param  array{kind: string, username: string, password: string, privilege?: string, database_name?: string}  $revealed
     */
    public function sendSiteCredentialToOwners(Domain $domain, array $revealed, User $actor): void
    {
        $recipients = $this->ownerRecipientEmails($domain, $actor);

        if ($recipients === []) {
            return;
        }

        $this->smtpSettings->mergeStoredIntoConfig();

        $kind = $revealed['kind'] ?? 'credential';
        $kindLabel = $kind === 'sftp' ? 'SFTP' : 'database';

        $detailLines = [
            "Utente: {$revealed['username']}",
            "Password: {$revealed['password']}",
        ];

        if (isset($revealed['database_name'])) {
            $detailLines[] = "Database: {$revealed['database_name']}";
        }

        if (isset($revealed['privilege'])) {
            $detailLines[] = "Permessi: {$revealed['privilege']}";
        }

        foreach ($recipients as $email) {
            Mail::to($email)->send(new SiteHostingCredentialMail(
                domainFqdn: $domain->fqdn,
                credentialKindLabel: $kindLabel,
                detailLines: $detailLines,
            ));
        }

        $this->audit->record('domain.site_credential.emailed', $domain, [
            'fqdn' => $domain->fqdn,
            'kind' => $kind,
            'recipients' => $recipients,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ownerRecipientEmails(Domain $domain, User $actor): array
    {
        $emails = $domain->members()
            ->wherePivot('role', DomainMemberRole::Owner->value)
            ->pluck('email')
            ->all();

        if ($emails === [] && $actor->email !== '') {
            $emails = [$actor->email];
        }

        return array_values(array_unique(array_filter($emails, fn (mixed $email): bool => is_string($email) && $email !== '')));
    }
}
