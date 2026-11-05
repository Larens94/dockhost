<?php

// DomainSftpFileController.php — Domain SFTP jail file browser (panel UI API).
//
// exports: DomainSftpFileController | DomainSftpFileController::index(...) | DomainSftpFileController::mkdir(...) | DomainSftpFileController::upload(...) | DomainSftpFileController::destroy(...) | DomainSftpFileController::download(...)
// used_by: routes/web.php
// rules:   SftpUser must belong to domain; paths validated in SftpJailFileManager.
// agent:   composer-2.5-fast | cursor | 2026-11-05 | s_sftp_files | Plesk-like browser

namespace App\Http\Controllers;

use App\Http\Requests\MutateDomainSftpUserRequest;
use App\Http\Requests\UploadDomainSftpFileRequest;
use App\Models\Domain;
use App\Models\SftpUser;
use App\Services\Audit\AuditLogger;
use App\Services\Infra\SftpJailFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DomainSftpFileController extends Controller
{
    public function index(
        MutateDomainSftpUserRequest $request,
        Domain $domain,
        SftpUser $sftpUser,
        SftpJailFileManager $files,
    ): JsonResponse {
        $request->userBelongsToDomain($domain, $sftpUser);

        $path = (string) $request->query('path', '');

        return response()->json($files->list($sftpUser, $path));
    }

    public function mkdir(
        MutateDomainSftpUserRequest $request,
        Domain $domain,
        SftpUser $sftpUser,
        SftpJailFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $request->userBelongsToDomain($domain, $sftpUser);

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:512'],
        ]);

        $files->mkdir($sftpUser, (string) $validated['path']);

        $audit->record('domain.sftp.mkdir', $domain, [
            'sftp_user_id' => $sftpUser->id,
            'path' => (string) $validated['path'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function upload(
        UploadDomainSftpFileRequest $request,
        Domain $domain,
        SftpUser $sftpUser,
        SftpJailFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $request->userBelongsToDomain($domain, $sftpUser);

        $validated = $request->validated();
        $uploaded = $request->file('file');

        if (! $uploaded) {
            return response()->json(['message' => 'No file'], 422);
        }

        $files->upload(
            $sftpUser,
            (string) ($validated['path'] ?? ''),
            (string) $uploaded->getClientOriginalName(),
            (string) file_get_contents($uploaded->getRealPath()),
        );

        $audit->record('domain.sftp.upload', $domain, [
            'sftp_user_id' => $sftpUser->id,
            'path' => (string) ($validated['path'] ?? ''),
            'filename' => (string) $uploaded->getClientOriginalName(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(
        MutateDomainSftpUserRequest $request,
        Domain $domain,
        SftpUser $sftpUser,
        SftpJailFileManager $files,
        AuditLogger $audit,
    ): JsonResponse {
        $request->userBelongsToDomain($domain, $sftpUser);

        $validated = $request->validate([
            'path' => ['required', 'string', 'max:512'],
        ]);

        $files->delete($sftpUser, (string) $validated['path']);

        $audit->record('domain.sftp.delete', $domain, [
            'sftp_user_id' => $sftpUser->id,
            'path' => (string) $validated['path'],
        ]);

        return response()->json(['ok' => true]);
    }

    public function download(
        MutateDomainSftpUserRequest $request,
        Domain $domain,
        SftpUser $sftpUser,
        SftpJailFileManager $files,
    ): StreamedResponse {
        $request->userBelongsToDomain($domain, $sftpUser);

        $path = (string) $request->query('path', '');
        $stream = $files->downloadStream($sftpUser, $path);
        $filename = basename(str_replace('\\', '/', $path));

        return Response::streamDownload(function () use ($stream): void {
            while (! $stream->eof()) {
                echo $stream->read(65536);
            }
        }, $filename);
    }
}
