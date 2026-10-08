<?php

// PanelSmtpTestMail.php — Plain-text SMTP test message for panel admin.
//
// exports: PanelSmtpTestMail
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   Subject MUST include "DokHosts prova SMTP pannello" + timestamp. Body via mail.plain-text view — NEVER Content textString (unsupported named parameter).
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | Plain test mailable for panel SMTP.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_panel_fix | Distinct subject with timestamp for log grep.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | Content text view mail.plain-text — textString unsupported on installed Laravel.

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PanelSmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $bodyLine,
        public string $subjectTimestamp,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'DokHosts prova SMTP pannello '.$this->subjectTimestamp,
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.plain-text',
            with: [
                'body' => $this->bodyLine,
            ],
        );
    }
}
