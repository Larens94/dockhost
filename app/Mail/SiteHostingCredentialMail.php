<?php

// SiteHostingCredentialMail.php — One-time site DB or SFTP credentials for a domain.
//
// exports: SiteHostingCredentialMail
// used_by: app/Services/Panel/DomainAccessMailer.php
// rules:   Plaintext password ONLY in email body at creation time — never log or audit password.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_access_mail | Site credential delivery mailable.

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SiteHostingCredentialMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $detailLines
     */
    public function __construct(
        public string $domainFqdn,
        public string $credentialKindLabel,
        public array $detailLines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Credenziali {$this->credentialKindLabel} — {$this->domainFqdn}",
        );
    }

    public function content(): Content
    {
        $lines = [
            "Nuove credenziali {$this->credentialKindLabel} per l'hosting {$this->domainFqdn}:",
            '',
            ...$this->detailLines,
            '',
            'Conservale in un posto sicuro. Il pannello non le mostra di nuovo in chiaro.',
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
