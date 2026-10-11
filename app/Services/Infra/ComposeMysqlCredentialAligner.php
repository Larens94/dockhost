<?php

// ComposeMysqlCredentialAligner.php — Copies live Dokploy compose MYSQL_* onto the panel row.
//
// exports: ComposeMysqlCredentialAligner | ComposeMysqlCredentialAligner::align(Infrastructure $infrastructure): bool | ComposeMysqlCredentialAligner::parseEnv(string $raw): array | ComposeMysqlCredentialAligner::composeMysqlEnv(Infrastructure $infrastructure): array
// used_by: app/Console/Commands/AlignMysqlFromComposeCommand.php
//         app/Providers/AppServiceProvider.php
//         app/Services/Infra/InfrastructureProvisioner.php
//         app/Services/Infra/MysqlCredentialHealer.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Unit/ComposeMysqlCredentialAlignerTest.php
//         tests/Unit/MysqlProvisionerConnectErrorTest.php
// rules:   Align panel FROM compose env only — never invent new passwords here.
//          Sync both MYSQL_APP_PASSWORD and MYSQL_ROOT_PASSWORD when present.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer-2.5-fast | cursor | 2026-10-11 | s_mysql_align_root | Also sync root password from compose
// message:

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use Throwable;

class ComposeMysqlCredentialAligner
{
    public function __construct(private DokployClient $dokploy) {}

    /**
     * Copy MYSQL_APP_* / MYSQL_ROOT_PASSWORD from the live Dokploy compose env onto the panel row.
     * Does not change the MariaDB datadir — only the panel's encrypted copy.
     */
    public function align(Infrastructure $infrastructure): bool
    {
        $env = $this->composeMysqlEnv($infrastructure);

        if ($env === []) {
            return false;
        }

        $user = ($env['MYSQL_APP_USER'] ?? '') !== '' ? (string) $env['MYSQL_APP_USER'] : 'infra';
        $appPassword = (string) ($env['MYSQL_APP_PASSWORD'] ?? '');
        $rootPassword = (string) ($env['MYSQL_ROOT_PASSWORD'] ?? '');

        if ($appPassword === '' && $rootPassword === '') {
            return false;
        }

        $updates = [];

        if ($appPassword !== '') {
            $currentUser = (string) $infrastructure->mysql_admin_user;
            $currentPassword = (string) $infrastructure->mysql_admin_password;

            if ($currentUser !== $user || $currentPassword !== $appPassword) {
                $updates['mysql_admin_user'] = $user;
                $updates['mysql_admin_password'] = $appPassword;
            }
        }

        if ($rootPassword !== '' && (string) $infrastructure->mysql_root_password !== $rootPassword) {
            $updates['mysql_root_password'] = $rootPassword;
        }

        if ($updates === []) {
            return false;
        }

        $infrastructure->update($updates);

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function composeMysqlEnv(Infrastructure $infrastructure): array
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            return [];
        }

        try {
            $compose = $this->dokploy->getCompose($composeId);
        } catch (Throwable) {
            return [];
        }

        return $this->parseEnv(is_string($compose['env'] ?? null) ? $compose['env'] : '');
    }

    /**
     * @return array<string, string>
     */
    public function parseEnv(string $raw): array
    {
        $out = [];

        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $out[trim($name)] = trim($value, "\"'");
        }

        return $out;
    }
}
