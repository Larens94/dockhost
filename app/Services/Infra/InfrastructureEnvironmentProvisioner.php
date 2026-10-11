<?php

// InfrastructureEnvironmentProvisioner.php — Create Dokploy environments under an infra project.
//
// exports: InfrastructureEnvironmentProvisioner | InfrastructureEnvironmentProvisioner::ensureDefaultProduction(Infrastructure $infrastructure): InfrastructureEnvironment | InfrastructureEnvironmentProvisioner::create(Infrastructure $infrastructure, string $name): InfrastructureEnvironment
// used_by: app/Http/Controllers/InfrastructureController.php
//         app/Services/Infra/InfrastructureProvisioner.php
// rules:   production is default and reuses infrastructure.dokploy_environment_id / compose_id.
//          Extra envs call environment.create; compose clone is a follow-up (suffix slug) — not invented API.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_infra_env | Create named Dokploy environments from the panel
// message:

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Models\InfrastructureEnvironment;
use App\Services\Dokploy\DokployClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class InfrastructureEnvironmentProvisioner
{
    public function __construct(private DokployClient $dokploy) {}

    public function ensureDefaultProduction(Infrastructure $infrastructure): InfrastructureEnvironment
    {
        $existing = $infrastructure->environments()->where('slug', 'production')->first();

        if ($existing instanceof InfrastructureEnvironment) {
            return $existing;
        }

        return InfrastructureEnvironment::query()->create([
            'infrastructure_id' => $infrastructure->id,
            'name' => 'production',
            'slug' => 'production',
            'dokploy_environment_id' => $infrastructure->dokploy_environment_id,
            'dokploy_compose_id' => $infrastructure->dokploy_compose_id,
            'is_default' => true,
        ]);
    }

    public function create(Infrastructure $infrastructure, string $name): InfrastructureEnvironment
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'Nome environment non valido.',
            ]);
        }

        if ($infrastructure->environments()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Esiste già un environment «'.$slug.'» su '.$infrastructure->slug.'.',
            ]);
        }

        $projectId = $infrastructure->dokploy_project_id;

        if (! is_string($projectId) || $projectId === '') {
            throw ValidationException::withMessages([
                'name' => 'Infrastruttura senza progetto Dokploy.',
            ]);
        }

        try {
            $created = $this->dokploy->createEnvironment([
                'name' => $name,
                'projectId' => $projectId,
            ]);
            $environmentId = $this->dokploy->idFrom($created, 'environmentId');
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'name' => $this->dokploy->errorMessage($exception),
            ]);
        }

        return InfrastructureEnvironment::query()->create([
            'infrastructure_id' => $infrastructure->id,
            'name' => $name,
            'slug' => $slug,
            'dokploy_environment_id' => $environmentId,
            'dokploy_compose_id' => null,
            'is_default' => false,
        ]);
    }
}
