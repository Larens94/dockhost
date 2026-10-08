<?php

// DomainMemberInviteMail.php — Italian invite to set panel password for a domain.
//
// exports: DomainMemberInviteMail
// used_by: app/Services/Panel/DomainAccessMailer.php
// rules:   MUST NOT include plaintext password. Body contains set-password URL only.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Invite mailable for domain Accessi.

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DomainMemberInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $inviteeName,
        public string $inviterName,
        public string $domainFqdn,
        public string $setPasswordUrl,
        public string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invito al pannello DokHosts — {$this->domainFqdn}",
        );
    }

    public function content(): Content
    {
        $lines = [
            "Ciao {$this->inviteeName},",
            '',
            "{$this->inviterName} ti ha invitato ad accedere al pannello DokHosts per l'hosting {$this->domainFqdn} (ruolo: {$this->roleLabel}).",
            '',
            'Per impostare la password e accedere, apri questo link (valido per un tempo limitato):',
            $this->setPasswordUrl,
            '',
            'Se non ti aspettavi questo messaggio, puoi ignorarlo.',
            '',
            '— DokHosts',
        ];

        return new Content(
            text: 'mail.plain-text',
            with: [
                'body' => implode("\n", $lines),
            ],
        );
    }
}
