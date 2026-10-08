{{-- plain-text.blade.php — Renders a plain-text mail body without HTML.

  exports: (view) mail.plain-text
  used_by: app/Mail/PanelSmtpTestMail.php, app/Mail/DomainMemberInviteMail.php, app/Mail/DomainMemberAccessGrantedMail.php, app/Mail/SiteHostingCredentialMail.php
  rules:   MUST output $body only — no HTML tags. Caller passes full plain-text content.
  agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | Replace invalid Content textString with text view.
--}}
{{ $body }}
