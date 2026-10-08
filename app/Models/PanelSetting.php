<?php

// PanelSetting.php — Key-value panel configuration persisted in the database.
//
// exports: PanelSetting | PanelSetting::value(string $key): ?string | PanelSetting::put(string $key, string $value): void
// used_by: app/Services/Panel/PanelGitLabCredentialStore.php
//         app/Providers/AppServiceProvider.php
// rules:   gitlab.token_encrypted MUST stay encrypted via Crypt — never log or expose in JSON. Env GITLAB_* overrides DB when set.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | Panel-wide settings store for GitLab PAT.
// message:

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelSetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public static function value(string $key): ?string
    {
        $row = static::query()->find($key);

        if ($row === null) {
            return null;
        }

        $stored = $row->value;

        return is_string($stored) && $stored !== '' ? $stored : null;
    }

    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
