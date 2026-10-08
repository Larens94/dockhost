<?php

// PanelSmtpSettingsController.php — Admin SMTP principale settings page and Dokploy sync.
//
// exports: PanelSmtpSettingsController | PanelSmtpSettingsController::index(PanelSmtpSettingsStore $store): Response | PanelSmtpSettingsController::update(UpdatePanelSmtpSettingsRequest $request, PanelSmtpSettingsStore $store, PanelSmtpSettingsSync $sync): RedirectResponse | PanelSmtpSettingsController::redeploy(DokployClient $dokploy, PanelApplicationResolver $panelApplication): RedirectResponse | PanelSmtpSettingsController::test(SendPanelSmtpTestRequest $request, PanelSmtpMailSender $sender, AuditLogger $audit): RedirectResponse
// used_by: routes/web.php
// rules:   Never log or return mail_password. Flash redeploy hint after Dokploy env sync. Test mail MUST use PanelSmtpMailSender; success only when real SMTP accepts (not log/array mailer).
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | SMTP principale Inertia page + save + optional redeploy.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | POST test sends PanelSmtpTestMail + smtp.test audit.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_panel_fix | Delegate test send to PanelSmtpMailSender with stderr audit log.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_text_fix | smtp_test_feedback flash + success includes Oggetto line.

namespace App\Http\Controllers;

use App\Http\Requests\SendPanelSmtpTestRequest;
use App\Http\Requests\UpdatePanelSmtpSettingsRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Dokploy\DokployClient;
use App\Services\Dokploy\PanelApplicationResolver;
use App\Services\Dokploy\PanelSmtpSettingsSync;
use App\Services\Panel\PanelSmtpMailSender;
use App\Services\Panel\PanelSmtpSettingsStore;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PanelSmtpSettingsController extends Controller
{
    public function index(PanelSmtpSettingsStore $store): Response
    {
        return Inertia::render('Panel/Smtp/Index', [
            'settings' => $store->formPayload(),
        ]);
    }

    public function update(
        UpdatePanelSmtpSettingsRequest $request,
        PanelSmtpSettingsStore $store,
        PanelSmtpSettingsSync $sync,
        AuditLogger $audit,
    ): RedirectResponse {
        $validated = $request->validated();
        $plainPassword = $request->input('mail_password');

        $store->store($validated, is_string($plainPassword) ? $plainPassword : null);
        $syncResult = $sync->syncFromConfig();

        $audit->record('panel.smtp.save', null, [
            'provider_preset' => $validated['provider_preset'] ?? null,
            'sync_status' => $syncResult['status'] ?? null,
        ]);

        $message = __('panel.smtp.saved');
        if ($syncResult['status'] === 'synced' || $syncResult['status'] === 'skipped_unchanged') {
            $message = __('panel.smtp.saved_redeploy');
        } elseif ($syncResult['status'] === 'failed') {
            $message = __('panel.smtp.saved_sync_failed');
        }

        return redirect()
            ->route('panel.smtp.index')
            ->with('success', $message);
    }

    public function redeploy(
        DokployClient $dokploy,
        PanelApplicationResolver $panelApplication,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! filled(config('dokploy.url')) || ! filled(config('dokploy.api_key'))) {
            return redirect()
                ->route('panel.smtp.index')
                ->with('success', __('panel.smtp.dokploy_missing'));
        }

        $applicationId = $panelApplication->resolveApplicationId();

        if ($applicationId === null) {
            return redirect()
                ->route('panel.smtp.index')
                ->with('success', __('panel.smtp.app_missing'));
        }

        try {
            $dokploy->deploy(['applicationId' => $applicationId]);
            $audit->record('panel.smtp.redeploy', null, [
                'application_id' => $applicationId,
            ]);
        } catch (Throwable) {
            return redirect()
                ->route('panel.smtp.index')
                ->with('success', __('panel.smtp.redeploy_failed'));
        }

        return redirect()
            ->route('panel.smtp.index')
            ->with('success', __('panel.smtp.redeploy_started'));
    }

    public function test(
        SendPanelSmtpTestRequest $request,
        PanelSmtpMailSender $sender,
        AuditLogger $audit,
    ): RedirectResponse {
        $recipient = trim((string) $request->validated('test_email'));

        $bodyLine = __('panel.smtp.test_body').' '.now()->toDateTimeString();

        $result = $sender->sendTestMail($recipient, $bodyLine);

        if ($result['ok'] !== true) {
            $errorMessage = $result['error'] ?? __('panel.smtp.test_failed');

            return redirect()
                ->route('panel.smtp.index')
                ->with('error', $errorMessage)
                ->with('smtp_test_feedback', [
                    'type' => 'error',
                    'message' => $errorMessage,
                ]);
        }

        $audit->record('smtp.test', null, [
            'recipient' => $recipient,
            'mailer' => $result['mailer'] ?? null,
            'message_id' => $result['message_id'] ?? null,
        ]);

        $successMessage = __('panel.smtp.test_accepted');
        if (filled($result['subject'] ?? null)) {
            $successMessage .= ' '.__('panel.smtp.test_subject', ['subject' => $result['subject']]);
        }

        return redirect()
            ->route('panel.smtp.index')
            ->with('success', $successMessage)
            ->with('smtp_test_feedback', [
                'type' => 'success',
                'message' => $successMessage,
            ]);
    }
}
