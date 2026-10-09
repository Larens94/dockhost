<?php

// ResyncMysqlSiteUsersCommand.php — Re-applies panel MySQL site users on shared infra MariaDB.
//
// exports: ResyncMysqlSiteUsersCommand | attr:Signature | attr:Description | ResyncMysqlSiteUsersCommand::handle(MysqlProvisioner $mysql): int
// used_by: none
// rules:   Never print passwords. Use after deploy drift or phpMyAdmin 1045 for site users.
// agent:   composer-2.5-fast | cursor | 2026-10-09 | s_pma_native_pass | CLI resync for site DB users
// message:

namespace App\Console\Commands;

use App\Enums\DatabaseEngine;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('hosting:resync-mysql-site-users {--infra= : Slug infrastruttura, es. infra1} {--domain= : FQDN dominio, es. vibesbridge.com}')]
#[Description('Riallinea su MariaDB gli utenti MySQL salvati nel pannello (password + GRANT, mysql_native_password)')]
class ResyncMysqlSiteUsersCommand extends Command
{
    public function handle(MysqlProvisioner $mysql): int
    {
        $infraSlug = trim((string) $this->option('infra'));
        $fqdn = strtolower(trim((string) $this->option('domain')));

        if ($infraSlug === '' && $fqdn === '') {
            $this->error('Specifica --infra= o --domain=.');

            return self::FAILURE;
        }

        $query = DatabaseAccount::query()
            ->where('engine', DatabaseEngine::Mysql)
            ->with('infrastructure');

        if ($fqdn !== '') {
            $domain = Domain::query()->where('fqdn', $fqdn)->first();

            if (! $domain instanceof Domain) {
                $this->error('Dominio non trovato: '.$fqdn);

                return self::FAILURE;
            }

            $query->where('domain_id', $domain->id);
        }

        if ($infraSlug !== '') {
            $infrastructure = Infrastructure::query()->where('slug', $infraSlug)->first();

            if (! $infrastructure instanceof Infrastructure) {
                $this->error('Infrastruttura non trovata: '.$infraSlug);

                return self::FAILURE;
            }

            $query->where(function ($builder) use ($infrastructure, $infraSlug): void {
                $builder->where('infrastructure_id', $infrastructure->id)
                    ->orWhere('infra_slug', $infraSlug);
            });
        }

        $accounts = $query->orderBy('id')->get();

        if ($accounts->isEmpty()) {
            $this->warn('Nessun account MySQL da riallineare.');

            return self::SUCCESS;
        }

        $synced = 0;

        foreach ($accounts as $account) {
            try {
                $mysql->resyncDatabaseAccount($account);
                $synced++;
                $this->line('OK '.$account->username.' @ '.$account->database_name);
            } catch (Throwable $exception) {
                $this->error('FAIL '.$account->username.': '.$exception->getMessage());
            }
        }

        $this->info('Riallineati: '.$synced.' / '.$accounts->count().'.');

        return $synced === $accounts->count() ? self::SUCCESS : self::FAILURE;
    }
}
