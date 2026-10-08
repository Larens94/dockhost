<?php

// PanelSmtpMailSender.php — Sends panel SMTP test mail with diagnostics logging.
//
// exports: PanelSmtpMailSender | PanelSmtpMailSender::sendTestMail(string $recipient, string $bodyLine): array
// used_by: app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   mergeStoredIntoConfig + forgetMailers before send. MUST NOT log passwords. Reject log/array mailers with user-visible error. Log action smtp.test to default + stderr.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_panel_fix | Real SMTP guard, stderr audit logs, message id on success.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | Return subject line on successful send for UI flash.

namespace App\Services\Panel;

use App\Mail\PanelSmtpTestMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PanelSmtpMailSender
{
    public function __construct(
        private PanelSmtpSettingsStore $store,
        private SmtpExceptionMessageSanitizer $sanitizer,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     error?: string,
     *     mailer?: string,
     *     message_id?: string|null,
     * }
     */
    public function sendTestMail(string $recipient, string $bodyLine): array
    {
        $this->store->mergeStoredIntoConfig();
        Mail::forgetMailers();

        $mailerName = (string) config('mail.default');
        $transport = config('mail.mailers.'.$mailerName.'.transport');
        $transportName = is_string($transport) ? $transport : '';

        if ($this->isNonDeliveringMailer($mailerName, $transportName)) {
            $message = 'Il mailer attivo non invia email reali ('.$mailerName.'). Configura SMTP nel pannello o imposta MAIL_MAILER=smtp su Dokploy, poi ridistribuisci dokhosts.';
            $this->writeSmtpTestLog($mailerName, $recipient, 'rejected', $message);

            return ['ok' => false, 'error' => $message];
        }

        if ($mailerName === 'smtp' && ! $this->store->isSmtpMailerReadyForDelivery()) {
            $message = 'SMTP incompleto: host o password mancanti. Salva le impostazioni SMTP o verifica MAIL_* su Dokploy.';
            $this->writeSmtpTestLog($mailerName, $recipient, 'rejected', $message);

            return ['ok' => false, 'error' => $message];
        }

        $subjectTimestamp = now()->format('Y-m-d H:i:s P');
        $mailable = new PanelSmtpTestMail($bodyLine, $subjectTimestamp);

        try {
            $sentMessage = Mail::mailer($mailerName)->to($recipient)->send($mailable);
            $messageId = $sentMessage?->getMessageId();
            $this->writeSmtpTestLog($mailerName, $recipient, 'accepted', null, $messageId);

            return [
                'ok' => true,
                'mailer' => $mailerName,
                'message_id' => $messageId,
                'subject' => 'DokHosts prova SMTP pannello '.$subjectTimestamp,
            ];
        } catch (Throwable $exception) {
            $safeMessage = $this->sanitizer->sanitize($exception->getMessage());
            $this->writeSmtpTestLog($mailerName, $recipient, 'failed', $safeMessage);

            return ['ok' => false, 'error' => $safeMessage];
        }
    }

    private function isNonDeliveringMailer(string $mailerName, string $transport): bool
    {
        if (in_array($mailerName, ['log', 'array'], true)) {
            return true;
        }

        return in_array($transport, ['log', 'array'], true);
    }

    private function writeSmtpTestLog(
        string $mailerName,
        string $recipient,
        string $result,
        ?string $errorOrDetail = null,
        ?string $messageId = null,
    ): void {
        $smtpHost = config('mail.mailers.smtp.host');
        $smtpPort = config('mail.mailers.smtp.port');
        $scheme = config('mail.mailers.smtp.scheme');
        $encryption = $scheme === 'smtps' ? 'ssl' : 'tls';
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');
        $transport = config('mail.mailers.'.$mailerName.'.transport');

        $context = [
            'action' => 'smtp.test',
            'mailer' => $mailerName,
            'transport' => is_string($transport) ? $transport : null,
            'host' => is_string($smtpHost) ? $smtpHost : null,
            'port' => is_numeric($smtpPort) ? (int) $smtpPort : $smtpPort,
            'encryption' => $encryption,
            'from' => is_string($fromAddress) ? $fromAddress : null,
            'from_name' => is_string($fromName) ? $fromName : null,
            'to' => $recipient,
            'result' => $result,
        ];

        if ($messageId !== null && $messageId !== '') {
            $context['message_id'] = $messageId;
        }

        if ($errorOrDetail !== null && $errorOrDetail !== '') {
            $context['detail'] = $errorOrDetail;
        }

        $logMessage = 'smtp.test '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        Log::info($logMessage, $context);
        Log::channel('stderr')->info($logMessage, $context);
    }
}
