<?php

// AuditLog.php — Immutable panel audit trail entries.
//
// exports: AuditLog | AuditLog::user(): BelongsTo
// used_by: app/Services/Audit/AuditLogger.php
//         app/Http/Controllers/AuditLogController.php
// rules:   meta MUST NOT contain passwords, tokens, or API keys — callers sanitize.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_audit | audit_logs model.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'ip',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
