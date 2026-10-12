<?php

// InfraOrphanContainerStopper.php — Stops leftover Docker containers that steal ${slug}-* DNS on dokploy-network.
//
// exports: InfraOrphanContainerStopper | InfraOrphanContainerStopper::stopForSlug(string $slug, ?string $keepComposeAppName = null): int | InfraOrphanContainerStopper::isOrphanForSlug(string $containerName, string $slug, ?string $keepComposeAppName = null): bool
// used_by: app/Services/Infra/InfrastructureProvisioner.php
// rules:   Shared dokploy-network aliases (${slug}-mariadb etc.) must resolve to ONE live container.
//          After compose recreate Dokploy may leave old names (infra1-mariadb) or prior appName suffixes
//          (infra1-itxmbo-*) running; stop those before/after provision so phpMyAdmin cannot hit a ghost MariaDB.
//          When keepComposeAppName is set (updateStack), never stop containers belonging to that compose project.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_orphan_dns | Stop slug DNS collisions after recreate
// message:

namespace App\Services\Infra;

use App\Services\Dokploy\DokployClient;
use Throwable;

class InfraOrphanContainerStopper
{
    /**
     * Longest service tokens first so sftp-sync / sftp-users-init beat bare sftp.
     */
    private const SERVICE_TOKENS = [
        'sftp-users-init',
        'mysql-grants',
        'sftp-sync',
        'phpmyadmin',
        'postgres',
        'pgadmin',
        'mariadb',
        'minio',
        'redis',
        'sftp',
    ];

    public function __construct(private DokployClient $dokploy) {}

    /**
     * Stop running containers that claim this infra slug's hostnames/aliases but are not
     * the current Dokploy compose project (when $keepComposeAppName is provided).
     *
     * @return int Number of stop attempts that completed without throwing
     */
    public function stopForSlug(string $slug, ?string $keepComposeAppName = null): int
    {
        $slug = strtolower(trim($slug));

        if ($slug === '') {
            return 0;
        }

        $keep = filled($keepComposeAppName) ? strtolower(trim((string) $keepComposeAppName)) : null;
        $stopped = 0;

        try {
            $containers = $this->dokploy->listContainers();
        } catch (Throwable) {
            return 0;
        }

        foreach ($containers as $container) {
            if (! is_array($container)) {
                continue;
            }

            $name = $this->containerName($container);
            $id = $this->containerId($container);

            if ($name === '' || $id === '' || ! $this->looksRunning($container)) {
                continue;
            }

            if (! $this->isOrphanForSlug($name, $slug, $keep)) {
                continue;
            }

            try {
                $this->dokploy->stopContainer($id);
                $stopped++;
            } catch (Throwable) {
            }
        }

        return $stopped;
    }

    public function isOrphanForSlug(string $containerName, string $slug, ?string $keepComposeAppName = null): bool
    {
        $name = strtolower(ltrim(trim($containerName), '/'));
        $slug = strtolower(trim($slug));

        if ($name === '' || $slug === '' || ! $this->matchesSlugInfraService($name, $slug)) {
            return false;
        }

        $keep = filled($keepComposeAppName) ? strtolower(trim((string) $keepComposeAppName)) : null;

        if ($keep !== null && $keep !== '' && $this->belongsToComposeApp($name, $keep)) {
            return false;
        }

        return true;
    }

    private function matchesSlugInfraService(string $name, string $slug): bool
    {
        $services = implode('|', array_map(
            static fn (string $token): string => preg_quote($token, '/'),
            self::SERVICE_TOKENS,
        ));

        // Exact hostname-as-name (legacy): infra1-mariadb
        // Compose project: infra1-gj0cip-mariadb-1 or infra1-mariadb-1
        return preg_match(
            '/^'.preg_quote($slug, '/').'(?:-[a-z0-9]+)?-(?:'.$services.')(?:-\d+)?$/',
            $name,
        ) === 1;
    }

    private function belongsToComposeApp(string $name, string $composeAppName): bool
    {
        return $name === $composeAppName
            || str_starts_with($name, $composeAppName.'-');
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerName(array $container): string
    {
        $name = $container['name'] ?? $container['Name'] ?? $container['Names'] ?? '';

        if (is_array($name)) {
            $name = (string) ($name[0] ?? '');
        }

        return ltrim(trim((string) $name), '/');
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function containerId(array $container): string
    {
        foreach (['containerId', 'Id', 'ID', 'id'] as $key) {
            $value = $container[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $container
     */
    private function looksRunning(array $container): bool
    {
        $stateValue = $container['State'] ?? $container['state'] ?? null;
        $state = is_array($stateValue)
            ? strtolower((string) ($stateValue['Status'] ?? $stateValue['status'] ?? ''))
            : strtolower(trim((string) $stateValue));

        if ($state === 'running' || $state === 'healthy' || str_starts_with($state, 'running')) {
            return true;
        }

        $status = strtolower((string) ($container['Status'] ?? $container['status'] ?? ''));

        return str_starts_with($status, 'up') || str_contains($status, 'running');
    }
}
