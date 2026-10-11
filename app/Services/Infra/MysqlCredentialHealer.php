<?php

// MysqlCredentialHealer.php — Non-destructive MariaDB credential heal for an infra stack.
//
// exports: MysqlCredentialHealer | MysqlCredentialHealer::heal(Infrastructure $infrastructure): array
// used_by: app/Http/Controllers/InfrastructureController.php
//         app/Services/Infra/MysqlProvisioner.php
// rules:   Never wipe the datadir. Try panel passwords BEFORE aligning from compose — align overwrites
//          panel secrets and must not run when panel already works or when compose credentials are wrong.
//          If compose root works, ALTER USER infra to the working app password. Never print secrets.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_mysql_heal | Heal infra admin without volume reset
//          composer-2.5-fast | cursor | 2026-10-11 | s_mysql_heal_order | Try panel before compose align
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
        $appUser = (string) ($infrastructure->mysql_admin_user ?: 'infra');
        $appPassword = (string) $infrastructure->mysql_admin_password;
        $rootPassword = (string) $infrastructure->mysql_root_password;

        // 1) Panel credentials already work — do not overwrite from compose.
        if ($appPassword !== '' && $this->canLogin($infrastructure, $appUser, $appPassword)) {
            if ((string) $infrastructure->mysql_admin_user !== $appUser) {
                $infrastructure->update(['mysql_admin_user' => $appUser]);
            }

            return [
                'aligned_from_compose' => false,
                'infra_login_ok' => true,
                'root_login_ok' => false,
                'altered_infra_user' => false,
                'message' => 'Login admin MariaDB OK con le password del pannello. Puoi creare database su '.$infrastructure->slug.'.',
            ];
        }

        $compose = $this->aligner->composeMysqlEnv($infrastructure);
        $composeUser = ($compose['MYSQL_APP_USER'] ?? '') !== '' ? (string) $compose['MYSQL_APP_USER'] : 'infra';
        $composeApp = (string) ($compose['MYSQL_APP_PASSWORD'] ?? '');
        $composeRoot = (string) ($compose['MYSQL_ROOT_PASSWORD'] ?? '');

        // 2) Compose app password works — persist onto panel, then done.
        if ($composeApp !== '' && $this->canLogin($infrastructure, $composeUser, $composeApp)) {
            $this->aligner->align($infrastructure);
            $infrastructure->refresh();

            return [
                'aligned_from_compose' => true,
                'infra_login_ok' => true,
                'root_login_ok' => false,
                'altered_infra_user' => false,
                'message' => 'Password compose Dokploy accettate da MariaDB e salvate nel pannello. Puoi creare database su '.$infrastructure->slug.'.',
            ];
        }

        // 3) Root (panel or compose) can ALTER infra to a known app password.
        $rootCandidates = array_values(array_unique(array_filter([$rootPassword, $composeRoot], fn (string $p): bool => $p !== '')));
        $appTargetUser = $composeApp !== '' ? $composeUser : $appUser;
        $appTargetPassword = $composeApp !== '' ? $composeApp : $appPassword;

        if ($appTargetPassword === '') {
            throw ValidationException::withMessages([
                'heal' => 'Nessuna password admin MariaDB nel pannello né in MYSQL_APP_PASSWORD sul compose. Controlla Dokploy senza wipeare il volume.',
            ]);
        }

        foreach ($rootCandidates as $candidateRoot) {
            try {
                $root = $this->pdo($infrastructure, 'root', $candidateRoot);
            } catch (PDOException) {
                continue;
            }

            $usernameIdentifier = '`'.str_replace('`', '``', $appTargetUser).'`';
            $root->exec('SET old_passwords=0');
            $root->exec("CREATE USER IF NOT EXISTS {$usernameIdentifier}@'%' IDENTIFIED BY ".$root->quote($appTargetPassword));
            $root->exec("ALTER USER {$usernameIdentifier}@'%' IDENTIFIED BY ".$root->quote($appTargetPassword));
            $root->exec("GRANT ALL PRIVILEGES ON *.* TO {$usernameIdentifier}@'%' WITH GRANT OPTION");
            $root->exec('FLUSH PRIVILEGES');

            if (! $this->canLogin($infrastructure, $appTargetUser, $appTargetPassword)) {
                throw ValidationException::withMessages([
                    'heal' => 'ALTER USER eseguito ma il login infra fallisce ancora. Verifica rete dokploy-network e hostname '.$infrastructure->mysql_host.'.',
                ]);
            }

            $infrastructure->update([
                'mysql_admin_user' => $appTargetUser,
                'mysql_admin_password' => $appTargetPassword,
                'mysql_root_password' => $candidateRoot,
            ]);

            return [
                'aligned_from_compose' => $candidateRoot === $composeRoot && $composeRoot !== '',
                'infra_login_ok' => true,
                'root_login_ok' => true,
                'altered_infra_user' => true,
                'message' => 'Utente infra riallineato via root senza toccare il datadir. Puoi riprovare la creazione del database.',
            ];
        }

        throw ValidationException::withMessages([
            'heal' => 'Né infra né root accettano le password del pannello o del compose Dokploy. '
                .'Il datadir MariaDB è divergente: non aggiornare lo stack. '
                .'Ultima risorsa controllata: «Ricrea datadir MariaDB» (cancella i DB siti su questo stack).',
        ]);
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
