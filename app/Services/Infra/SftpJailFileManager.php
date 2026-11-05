<?php

// SftpJailFileManager.php — Panel → sftp-sync file API (jail-scoped list/upload/mkdir/delete/download).
//
// exports: SftpJailFileManager | SftpJailFileManager::list(SftpUser $user, string $relativePath): array | SftpJailFileManager::mkdir(SftpUser $user, string $relativePath): void | SftpJailFileManager::delete(SftpUser $user, string $relativePath): void | SftpJailFileManager::upload(SftpUser $user, string $relativeDir, string $filename, string $bytes): void
// used_by: app/Http/Controllers/DomainSftpFileController.php
// rules:   Panel MUST NOT mount infra data volumes; all I/O via Bearer sftp_sync_token to {slug}-sftp-sync.
//          Never log token or file bytes. relativePath must not contain '..'.
// agent:   composer-2.5-fast | cursor | 2026-11-05 | s_sftp_files | Plesk-like browser via sidecar

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Models\SftpUser;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SftpJailFileManager
{
    public const MAX_UPLOAD_BYTES = 64 * 1024 * 1024;

    public function list(SftpUser $user, string $relativePath = ''): array
    {
        $response = $this->post($user, '/files/list', [
            'jail' => $user->username,
            'path' => $this->normalizeRelativePath($relativePath),
        ]);

        return [
            'path' => (string) ($response['path'] ?? ''),
            'entries' => is_array($response['entries'] ?? null) ? $response['entries'] : [],
        ];
    }

    public function mkdir(SftpUser $user, string $relativePath): void
    {
        $this->post($user, '/files/mkdir', [
            'jail' => $user->username,
            'path' => $this->normalizeRelativePath($relativePath),
        ]);
    }

    public function delete(SftpUser $user, string $relativePath): void
    {
        $this->post($user, '/files/delete', [
            'jail' => $user->username,
            'path' => $this->normalizeRelativePath($relativePath),
        ]);
    }

    /**
     * @return resource
     */
    public function downloadStream(SftpUser $user, string $relativePath)
    {
        $infrastructure = $this->requireInfrastructure($user);
        $token = (string) ($infrastructure->sftp_sync_token ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_sidecar_missing'),
            ]);
        }

        $path = $this->normalizeRelativePath($relativePath);
        $url = $infrastructure->sftpSyncApiBaseUrl().'/files/download?'.http_build_query([
            'jail' => $user->username,
            'path' => $path,
        ]);

        try {
            $response = Http::timeout(120)
                ->withToken($token)
                ->withOptions(['stream' => true])
                ->get($url);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_unreachable'),
            ]);
        }

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_download_failed'),
            ]);
        }

        return $response->toPsrResponse()->getBody();
    }

    public function upload(SftpUser $user, string $relativeDir, string $filename, string $bytes): void
    {
        if (strlen($bytes) > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_upload_too_large'),
            ]);
        }

        $infrastructure = $this->requireInfrastructure($user);
        $token = (string) ($infrastructure->sftp_sync_token ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_sidecar_missing'),
            ]);
        }

        $filename = $this->sanitizeFilename($filename);
        $relativeDir = $this->normalizeRelativePath($relativeDir);

        try {
            Http::timeout(120)
                ->withToken($token)
                ->withHeaders([
                    'X-Jail' => $user->username,
                    'X-Relative-Dir' => $relativeDir,
                    'X-Filename' => $filename,
                    'Content-Type' => 'application/octet-stream',
                ])
                ->withBody($bytes, 'application/octet-stream')
                ->post($infrastructure->sftpSyncApiBaseUrl().'/files/upload')
                ->throw();
        } catch (RequestException) {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_upload_failed'),
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_unreachable'),
            ]);
        }
    }

    private function post(SftpUser $user, string $endpoint, array $payload): array
    {
        $infrastructure = $this->requireInfrastructure($user);
        $token = (string) ($infrastructure->sftp_sync_token ?? '');

        if ($token === '') {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_sidecar_missing'),
            ]);
        }

        try {
            $response = Http::timeout(30)
                ->withToken($token)
                ->acceptJson()
                ->post($infrastructure->sftpSyncApiBaseUrl().$endpoint, $payload)
                ->throw()
                ->json();
        } catch (RequestException) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_operation_failed'),
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_unreachable'),
            ]);
        }

        return is_array($response) ? $response : [];
    }

    private function requireInfrastructure(SftpUser $user): Infrastructure
    {
        $infrastructure = $user->infrastructure;

        if (! $infrastructure instanceof Infrastructure) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_infra_missing'),
            ]);
        }

        return $infrastructure;
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $normalized = ltrim($normalized, '/');

        if ($normalized === '') {
            return '';
        }

        if (str_contains($normalized, '..')) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_invalid_path'),
            ]);
        }

        if (strlen($normalized) > 512) {
            throw ValidationException::withMessages([
                'path' => __('panel.domains.show.sftp_files_invalid_path'),
            ]);
        }

        return $normalized;
    }

    private function sanitizeFilename(string $filename): string
    {
        $name = basename(str_replace('\\', '/', trim($filename)));

        if ($name === '' || $name === '.' || $name === '..') {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_invalid_filename'),
            ]);
        }

        if (strlen($name) > 255) {
            throw ValidationException::withMessages([
                'file' => __('panel.domains.show.sftp_files_invalid_filename'),
            ]);
        }

        return $name;
    }
}
