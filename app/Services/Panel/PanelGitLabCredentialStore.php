<?php

// PanelGitLabCredentialStore.php — Persists panel GitLab URL and encrypted PAT; merges into runtime config.
//
// exports: PanelGitLabCredentialStore | PanelGitLabCredentialStore::mergeStoredIntoConfig(): void | PanelGitLabCredentialStore::store(string $url, string $token): void | PanelGitLabCredentialStore::catalogCacheEpoch(): string | PanelGitLabCredentialStore::bumpCatalogCacheEpoch(): void
// used_by: app/Providers/AppServiceProvider.php
//         app/Http/Controllers/PanelGitLabCredentialController.php
//         app/Services/Laravel/LaravelToolkitCommandDiscovery.php
// rules:   Env GITLAB_URL/GITLAB_TOKEN win over DB when non-empty. Token encrypted with Crypt::encryptString. Never log token.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_gitlab_url_default | Form default follows config services.gitlab.url (Silicore instance).
// message:

namespace App\Services\Panel;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class PanelGitLabCredentialStore
{
    public const KEY_URL = 'gitlab.url';

    public const KEY_TOKEN_ENCRYPTED = 'gitlab.token_encrypted';

    public const KEY_CATALOG_EPOCH = 'toolkit.catalog_cache_epoch';

    /**
     * Rules: MUST run on each request before GitLab clients read config; env overrides DB.
     */
    public function mergeStoredIntoConfig(): void
    {
        try {
            if (! Schema::hasTable('panel_settings')) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        if (! filled(env('GITLAB_URL'))) {
            $storedUrl = PanelSetting::value(self::KEY_URL);
            if (is_string($storedUrl) && $storedUrl !== '') {
                Config::set('services.gitlab.url', rtrim($storedUrl, '/'));
            }
        }

        if (! filled(env('GITLAB_TOKEN'))) {
            $decrypted = $this->decryptedTokenFromDatabase();
            if ($decrypted !== null) {
                Config::set('services.gitlab.token', $decrypted);
            }
        }
    }

    public function catalogCacheEpoch(): string
    {
        return PanelSetting::value(self::KEY_CATALOG_EPOCH) ?? '0';
    }

    public function bumpCatalogCacheEpoch(): void
    {
        $next = (string) ((int) $this->catalogCacheEpoch() + 1);
        PanelSetting::put(self::KEY_CATALOG_EPOCH, $next);
    }

    /**
     * @throws ValidationException
     */
    public function store(string $url, string $token): void
    {
        $normalizedUrl = rtrim(trim($url), '/');
        $token = trim($token);

        if ($normalizedUrl === '' || $token === '') {
            throw ValidationException::withMessages([
                'token' => 'URL GitLab e token sono obbligatori.',
            ]);
        }

        PanelSetting::put(self::KEY_URL, $normalizedUrl);
        PanelSetting::put(self::KEY_TOKEN_ENCRYPTED, Crypt::encryptString($token));
        $this->bumpCatalogCacheEpoch();

        Config::set('services.gitlab.url', $normalizedUrl);
        Config::set('services.gitlab.token', $token);
    }

    public function tokenConfiguredInDatabase(): bool
    {
        return $this->decryptedTokenFromDatabase() !== null;
    }

    public function defaultUrlForForm(): string
    {
        $fromConfig = config('services.gitlab.url');

        if (is_string($fromConfig) && $fromConfig !== '') {
            return rtrim($fromConfig, '/');
        }

        $stored = PanelSetting::value(self::KEY_URL);

        if (is_string($stored) && $stored !== '') {
            return rtrim($stored, '/');
        }

        return rtrim((string) config('services.gitlab.url', 'https://git.silicoreautomation.com'), '/');
    }

    private function decryptedTokenFromDatabase(): ?string
    {
        $encrypted = PanelSetting::value(self::KEY_TOKEN_ENCRYPTED);

        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }

        return $plain !== '' ? $plain : null;
    }
}
