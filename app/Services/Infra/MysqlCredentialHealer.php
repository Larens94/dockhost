<?php

// MysqlCredentialHealer.php — Non-destructive MariaDB credential heal for an infra stack.
//
// exports: MysqlCredentialHealer | MysqlCredentialHealer::heal(Infrastructure $infrastructure): array
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   Never wipe the datadir. Align panel from compose first. If root from compose/panel works,
//          ALTER USER infra to the compose/panel app password. Never print secrets.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_mysql_heal | Heal infra admin without volume reset
// message:

namespace App\Services\Infra;

use App\Models\Infrastructure;
use Illuminate\Validation\ValidationException;
use PDO;
use PDOException;
use Throwable;

class MysqlCredentialHealer
{
    public function __construct(
        private ComposeMysqlCredentialAligner $aligner,
    ) {}

    /**
     * @return array{aligned_from_compose: bool, infra_login_ok: bool, root_login_ok: bool, altered_infra_user: bool, message: string}
     */
    public function heal(Infrastructure $infrastructure): array
    {
        $aligned = $this->aligner->align($infrastructure);
        $infrastructure->refresh();

        $appUser = (string) ($infrastructure->mysql_admin_user ?: 'infra');
        $appPassword = (string) $infrastructure->mysql_admin_password;
        $rootPassword = (string) $infrastructure->mysql_root_password;

        if ($appPassword === '') {
            throw ValidationException::withMessages([
                'heal' => 'Password admin MariaDB assente dopo l’allineamento dal compose. Controlla MYSQL_APP_PASSWORD su Dokploy.',
            ]);
        }

        $infraOk = $this->canLogin($infrastructure, $appUser, $appPassword);
        if ($infraOk) {
            return [
                'aligned_from_compose' => $aligned,
                'infra_login_ok' => true,
                'root_login_ok' => false,
                'altered_infra_user' => false,
                'message' => 'Login admin MariaDB OK. Il pannello può creare database su '.$infrastructure->slug.'.',
            ];
        }

        if ($rootPassword === '') {
            throw ValidationException::withMessages([
                'heal' => 'Login infra fallito e password root assente. Imposta MYSQL_ROOT_PASSWORD nel compose Dokploy e riprova l’allineamento — senza wipe del volume.',
            ]);
        }

        try {
            $root = $this->pdo($infrastructure, 'root', $rootPassword);
        } catch (PDOException) {
            throw ValidationException::withMessages([
                'heal' => 'Né infra né root accettano le password del pannello/compose. Il datadir MariaDB è divergente: non aggiornare lo stack e non wipeare finché non recuperi una password root valida.',
            ]);
        }

        $usernameIdentifier = '`'.str_replace('`', '``', $appUser).'`';
        $root->exec('SET old_passwords=0');
        $root->exec("CREATE USER IF NOT EXISTS {$usernameIdentifier}@'%' IDENTIFIED BY ".$root->quote($appPassword));
        $root->exec("ALTER USER {$usernameIdentifier}@'%' IDENTIFIED BY ".$root->quote($appPassword));
        $root->exec("GRANT ALL PRIVILEGES ON *.* TO {$usernameIdentifier}@'%' WITH GRANT OPTION");
        $root->exec('FLUSH PRIVILEGES');

        if (! $this->canLogin($infrastructure, $appUser, $appPassword)) {
            throw ValidationException::withMessages([
                'heal' => 'ALTER USER eseguito ma il login infra fallisce ancora. Verifica rete dokploy-network e hostname '.$infrastructure->mysql_host.'.',
            ]);
        }

        return [
            'aligned_from_compose' => $aligned,
            'infra_login_ok' => true,
            'root_login_ok' => true,
            'altered_infra_user' => true,
            'message' => 'Utente infra riallineato via root senza toccare il datadir. Puoi riprovare la creazione del database.',
        ];
    }

    private function canLogin(Infrastructure $infrastructure, string $username, string $password): bool
    {
        try {
            $this->pdo($infrastructure, $username, $password);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function pdo(Infrastructure $infrastructure, string $username, string $password): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=utf8mb4',
            $infrastructure->mysql_host,
            $infrastructure->mysql_port,
        );

        return new PDO(
            $dsn,
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
