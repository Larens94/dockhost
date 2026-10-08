<?php

// AuditLogController.php — Admin audit log listing (latest 100).
//
// exports: AuditLogController | index
// used_by: routes/web.php
// rules:   Admin only — members never see global audit. No secrets in meta display.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | /panel/audit page.

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'ip' => $log->ip,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
            ]);

        return Inertia::render('Panel/Audit/Index', [
            'logs' => $logs,
        ]);
    }
}
