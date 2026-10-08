<?php


// ComposeMysqlCredentialAligner.php — ComposeMysqlCredentialAligner module.
//
// exports: ComposeMysqlCredentialAligner | ComposeMysqlCredentialAligner::align(Infrastructure $infrastructure): bool | ComposeMysqlCredentialAligner::parseEnv(string $raw): array
// used_by: app/Console/Commands/AlignMysqlFromComposeCommand.php
//         app/Providers/AppServiceProvider.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Unit/ComposeMysqlCredentialAlignerTest.php
//         tests/Unit/MysqlProvisionerConnectErrorTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use Throwable;

class ComposeMysqlCredentialAligner
{
    public function __construct(private DokployClient $dokploy) {}

    /**
     * Copy MYSQL_APP_* from the live Dokploy compose env onto the panel row.
     * MariaDB after GRANT_OK uses those values; the encrypted panel copy can drift.
     */
    public function align(Infrastructure $infrastructure): bool
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            return false;
        }

        try {
            $compose = $this->dokploy->getCompose($composeId);
        } catch (Throwable) {
            return false;
        }

        $env = $this->parseEnv(is_string($compose['env'] ?? null) ? $compose['env'] : '');
        $user = $env['MYSQL_APP_USER'] ?? '';
        $password = $env['MYSQL_APP_PASSWORD'] ?? '';

        if ($password === '') {
            return false;
        }

        $user = $user !== '' ? $user : 'infra';
        $currentUser = (string) $infrastructure->mysql_admin_user;
        $currentPassword = (string) $infrastructure->mysql_admin_password;

        if ($currentUser === $user && $currentPassword === $password) {
            return false;
        }

        $infrastructure->update([
            'mysql_admin_user' => $user,
            'mysql_admin_password' => $password,
        ]);

        return true;
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
