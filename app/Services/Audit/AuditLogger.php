<?php

// AuditLogger.php — Record important panel actions to audit_logs.
//
// exports: AuditLogger | AuditLogger::record(string $action, ?Model $subject = null, array $meta = []): AuditLog
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/DomainMemberController.php
//         app/Http/Controllers/PanelSmtpSettingsController.php
// rules:   Strip keys matching /password|secret|token|api_key/i from meta. Never log raw credentials.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | Central audit writer.

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Rules: meta is sanitized — forbidden key substrings removed.
     */
    public function record(string $action, ?Model $subject = null, array $meta = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject !== null ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'ip' => Request::ip(),
            'meta' => $this->sanitizeMeta($meta),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function sanitizeMeta(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (preg_match('/password|secret|token|api_key|recovery/i', $key)) {
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizeMeta($value);

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
