<?php

namespace App\Support;

/**
 * Stack-aware Application Toolkit definitions (Plesk Laravel Toolkit style).
 * Modules marked owner=dokploy are deep-links only — not rebuilt in DockHost.
 */
class ApplicationToolkit
{
    /**
     * @return array{title: string, tabs: list<array<string, mixed>>, quick_links: list<array<string, mixed>>, settings: list<array<string, mixed>>}
     */
    public static function forStack(string $stack): array
    {
        return match ($stack) {
            'laravel' => self::laravel(),
            'wordpress' => self::wordpress(),
            'node' => self::node(),
            'static' => self::staticSite(),
            default => self::generic($stack),
        };
    }

    private static function laravel(): array
    {
        return [
            'title' => 'Laravel application',
            'tabs' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'owner' => 'dockhost'],
                ['id' => 'artisan', 'label' => 'Artisan', 'owner' => 'dockhost'],
                ['id' => 'composer', 'label' => 'Composer', 'owner' => 'dockhost'],
                ['id' => 'nodejs', 'label' => 'Node.js', 'owner' => 'dockhost'],
                ['id' => 'deployment', 'label' => 'Deployment', 'owner' => 'dokploy'],
                ['id' => 'schedule', 'label' => 'Scheduled Tasks', 'owner' => 'dockhost'],
                ['id' => 'queue', 'label' => 'Queue', 'owner' => 'dockhost'],
            ],
            'quick_links' => [
                ['id' => 'domain', 'label' => 'Manage domain', 'owner' => 'dokploy'],
                ['id' => 'logs', 'label' => 'Logs', 'owner' => 'dokploy'],
                ['id' => 'terminal', 'label' => 'Terminal', 'owner' => 'dokploy'],
            ],
            'settings' => [
                ['id' => 'env', 'label' => 'Environment variables (.env)', 'type' => 'action', 'owner' => 'dockhost'],
                ['id' => 'schedule_enabled', 'label' => 'Scheduled Tasks', 'type' => 'toggle', 'owner' => 'dockhost'],
                ['id' => 'queue_enabled', 'label' => 'Queue', 'type' => 'toggle', 'owner' => 'dockhost'],
                ['id' => 'maintenance', 'label' => 'Maintenance mode', 'type' => 'toggle', 'owner' => 'dockhost'],
            ],
        ];
    }

    private static function wordpress(): array
    {
        return [
            'title' => 'WordPress application',
            'tabs' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'owner' => 'dockhost'],
                ['id' => 'wp_cli', 'label' => 'WP-CLI', 'owner' => 'dockhost'],
                ['id' => 'plugins', 'label' => 'Plugins', 'owner' => 'dockhost'],
                ['id' => 'deployment', 'label' => 'Deployment', 'owner' => 'dokploy'],
            ],
            'quick_links' => [
                ['id' => 'domain', 'label' => 'Manage domain', 'owner' => 'dokploy'],
                ['id' => 'logs', 'label' => 'Logs', 'owner' => 'dokploy'],
                ['id' => 'terminal', 'label' => 'Terminal', 'owner' => 'dokploy'],
            ],
            'settings' => [
                ['id' => 'maintenance', 'label' => 'Maintenance mode', 'type' => 'toggle', 'owner' => 'dockhost'],
            ],
        ];
    }

    private static function node(): array
    {
        return [
            'title' => 'Node application',
            'tabs' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'owner' => 'dockhost'],
                ['id' => 'npm', 'label' => 'npm / pnpm', 'owner' => 'dockhost'],
                ['id' => 'process', 'label' => 'Process', 'owner' => 'dockhost'],
                ['id' => 'deployment', 'label' => 'Deployment', 'owner' => 'dokploy'],
            ],
            'quick_links' => [
                ['id' => 'domain', 'label' => 'Manage domain', 'owner' => 'dokploy'],
                ['id' => 'logs', 'label' => 'Logs', 'owner' => 'dokploy'],
                ['id' => 'terminal', 'label' => 'Terminal', 'owner' => 'dokploy'],
            ],
            'settings' => [
                ['id' => 'env', 'label' => 'Environment variables', 'type' => 'action', 'owner' => 'dockhost'],
            ],
        ];
    }

    private static function staticSite(): array
    {
        return [
            'title' => 'Static site',
            'tabs' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'owner' => 'dockhost'],
                ['id' => 'deployment', 'label' => 'Deployment', 'owner' => 'dokploy'],
            ],
            'quick_links' => [
                ['id' => 'domain', 'label' => 'Manage domain', 'owner' => 'dokploy'],
                ['id' => 'logs', 'label' => 'Logs', 'owner' => 'dokploy'],
            ],
            'settings' => [],
        ];
    }

    private static function generic(string $stack): array
    {
        return [
            'title' => ucfirst($stack).' application',
            'tabs' => [
                ['id' => 'dashboard', 'label' => 'Dashboard', 'owner' => 'dockhost'],
                ['id' => 'deployment', 'label' => 'Deployment', 'owner' => 'dokploy'],
            ],
            'quick_links' => [
                ['id' => 'domain', 'label' => 'Manage domain', 'owner' => 'dokploy'],
                ['id' => 'logs', 'label' => 'Logs', 'owner' => 'dokploy'],
            ],
            'settings' => [],
        ];
    }
}
