<?php


// AlignMysqlFromComposeCommand.php — AlignMysqlFromComposeCommand module.
//
// exports: AlignMysqlFromComposeCommand | attr:Signature | attr:Description | AlignMysqlFromComposeCommand::handle(ComposeMysqlCredentialAligner $aligner): int
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Infrastructure;
use App\Services\Infra\ComposeMysqlCredentialAligner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('infra:align-mysql-from-compose {--prune-fqdn= : Elimina un dominio orfano senza database né app Dokploy}')]
#[Description('Allinea le password MariaDB del pannello a quelle live del compose Dokploy')]
class AlignMysqlFromComposeCommand extends Command
{
    public function handle(ComposeMysqlCredentialAligner $aligner): int
    {
        $updated = 0;

        foreach (Infrastructure::query()->panelManaged()->whereNotNull('dokploy_compose_id')->get() as $infrastructure) {
            if ($aligner->align($infrastructure)) {
                $updated++;
                $this->info('Allineate credenziali MariaDB per '.$infrastructure->slug.'.');
            }
        }

        $this->info('Stack aggiornati: '.$updated.'.');

        $fqdn = strtolower(trim((string) $this->option('prune-fqdn')));

        if ($fqdn !== '') {
            $this->pruneOrphanDomain($fqdn);
        }

        return self::SUCCESS;
    }

    private function pruneOrphanDomain(string $fqdn): void
    {
        $domain = Domain::query()->where('fqdn', $fqdn)->first();

        if (! $domain instanceof Domain) {
            $this->info('Nessun dominio «'.$fqdn.'» da pulire.');

            return;
        }

        if ($domain->databaseAccounts()->exists() || $domain->dokployApplication()->exists()) {
            $this->warn('Dominio «'.$fqdn.'» ha database o app Dokploy: non lo elimino.');

            return;
        }

        $domain->delete();
        $this->info('Eliminato dominio orfano «'.$fqdn.'».');
    }
}
