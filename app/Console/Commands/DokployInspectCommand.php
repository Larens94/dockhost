<?php


// DokployInspectCommand.php — DokployInspectCommand module.
//
// exports: DokployInspectCommand | attr:Signature | attr:Description | DokployInspectCommand::handle(ComposeRuntimeInspector $inspector): int
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Console\Commands;

use App\Models\Infrastructure;
use App\Services\Infra\ComposeRuntimeInspector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('dokploy:inspect {slug : Slug o id dell’infrastruttura} {--service= : Servizio compose (ripetibile con virgole)} {--tail=300 : Righe di log} {--all : Log di tutti i container} {--json : Output JSON}')]
#[Description('Legge stato, container e log Dokploy di uno stack e stampa una diagnosi')]
class DokployInspectCommand extends Command
{
    public function handle(ComposeRuntimeInspector $inspector): int
    {
        $slug = (string) $this->argument('slug');
        $query = Infrastructure::query();
        $infrastructure = ctype_digit($slug)
            ? $query->where('id', (int) $slug)->orWhere('slug', $slug)->first()
            : $query->where('slug', $slug)->first();

        if ($infrastructure === null) {
            $this->error('Infrastruttura «'.$slug.'» non trovata.');

            return self::FAILURE;
        }

        $serviceOption = $this->option('service');
        $services = is_string($serviceOption) && $serviceOption !== ''
            ? array_values(array_filter(array_map(trim(...), explode(',', $serviceOption))))
            : null;

        try {
            $report = $inspector->inspect(
                $infrastructure,
                max(1, min(10000, (int) $this->option('tail'))),
                $services,
                (bool) $this->option('all'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info($report['slug'].'  status='.$report['status'].'  compose='.$report['compose_id']);

        foreach ($report['findings'] as $finding) {
            $label = strtoupper($finding['severity']).' '.$finding['code'];
            $line = $label.': '.$finding['message'];

            match ($finding['severity']) {
                'error' => $this->error($line),
                'ok' => $this->info($line),
                default => $this->warn($line),
            };
        }

        foreach ($report['logs'] as $log) {
            $this->newLine();
            $this->line('## '.($log['service'] ?: $log['name']).'  '.$log['state'].'  '.$log['status']);

            if (is_string($log['error']) && $log['error'] !== '') {
                $this->error($log['error']);
            }

            if ($log['body'] !== '') {
                $this->line($log['body']);
            }
        }

        return self::SUCCESS;
    }
}
