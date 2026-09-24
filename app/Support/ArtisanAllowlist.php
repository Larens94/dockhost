<?php

namespace App\Support;

class ArtisanAllowlist
{
    /**
     * Commands DockHost may record for a site runtime.
     *
     * @return list<string>
     */
    public static function commands(): array
    {
        return [
            'about',
            'migrate --force',
            'optimize',
            'optimize:clear',
            'config:cache',
            'route:cache',
            'view:cache',
            'queue:restart',
            'storage:link',
            'down',
            'up',
        ];
    }

    public static function accepts(string $command): bool
    {
        return in_array(trim($command), self::commands(), true);
    }
}
