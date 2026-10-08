<?php

// DomainMemberAccessGrantedMail.php — Notify existing user of new domain access.
//
// exports: DomainMemberAccessGrantedMail
// used_by: app/Services/Panel/DomainAccessMailer.php
// rules:   No password in body — login URL only.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Access granted mailable for existing users.

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DomainMemberAccessGrantedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $memberName,
        public string $inviterName,
        public string $domainFqdn,
        public string $loginUrl,
        public string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Accesso al pannello DokHosts — {$this->domainFqdn}",
        );
    }

    public function content(): Content
    {
        $lines = [
            "Ciao {$this->memberName},",
            '',
            "{$this->inviterName} ti ha concesso accesso al pannello DokHosts per l'hosting {$this->domainFqdn} (ruolo: {$this->roleLabel}).",
            '',
            "Accedi da: {$this->loginUrl}",
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
