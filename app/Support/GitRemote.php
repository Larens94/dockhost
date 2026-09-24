<?php

namespace App\Support;

use RuntimeException;

class GitRemote
{
    /**
     * @return array{url: string, branch: string}
     */
    public static function forDokploy(string $repository, string $branch = 'main'): array
    {
        $url = self::https(trim($repository));
        $branch = $branch !== '' ? $branch : 'main';

        if (! preg_match('#^https://#', $url)) {
            throw new RuntimeException('Repository must be an https or git@ URL.');
        }

        if (! preg_match('/^[a-zA-Z0-9._\-\/]+$/', $branch)) {
            throw new RuntimeException('Git branch contains unsupported characters.');
        }

        return [
            'url' => $url,
            'branch' => $branch,
        ];
    }

    private static function https(string $repository): string
    {
        if (preg_match('#^git@([^:]+):(.+)$#', $repository, $matches)) {
            return 'https://'.$matches[1].'/'.$matches[2];
        }

        if (preg_match('#^ssh://git@([^/]+)/(.+)$#', $repository, $matches)) {
            return 'https://'.$matches[1].'/'.$matches[2];
        }

        return $repository;
    }
}
