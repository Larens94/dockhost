<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Support\OperatorError;
use Throwable;

class RecipeExecutor
{
    public function __construct(
        private TenantDatabaseProvisioner $databases,
        private TenantSftpProvisioner $sftp,
        private EnvironmentBuilder $environment,
        private InfrastructureDriver $driver,
        private PoolLedger $ledger,
        private AuditLogger $audit,
    ) {}

    public function execute(Site $site): void
    {
        $site->load('recipe');
        $site->status = 'provisioning';
        $site->last_error = null;
        $site->save();

        try {
            foreach ($this->steps($site) as $op) {
                $this->run($site, $op);
            }

            $site->refresh();

            if ($this->awaitsDokploy($site)) {
                $this->refreshDeployStatus($site);
            } else {
                $site->status = 'active';
                $site->last_error = null;
                $site->save();
            }

            $site->refresh();
            $this->audit->log(
                $site->status === 'active' ? 'site.provisioned' : 'site.deploy_pending',
                $site,
                ['domain' => $site->domain, 'status' => $site->status],
            );
        } catch (Throwable $exception) {
            $this->ledger->release($site);
            $site->status = 'failed';
            $site->last_error = OperatorError::present($exception->getMessage());
            $site->save();
            $this->audit->log('site.provision_failed', $site, [
                'domain' => $site->domain,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function steps(Site $site): array
    {
        $ops = collect($site->recipe?->steps ?? [])
            ->pluck('op')
            ->filter(fn ($op) => is_string($op) && $op !== '')
            ->values();

        if ($ops->isEmpty()) {
            $ops = collect([
                'ensure_database',
                'ensure_storage_path',
                'ensure_sftp_user',
                'deploy_application',
                'attach_domain_ssl',
            ]);
        }

        if (! $ops->contains('write_env')) {
            $deployAt = $ops->search('deploy_application');

            if ($deployAt === false) {
                $ops->push('write_env');
            } else {
                $ops->splice($deployAt, 0, ['write_env']);
            }
        }

        return $ops->values()->all();
    }

    private function run(Site $site, string $op): void
    {
        $options = $site->options ?? [];

        match ($op) {
            'ensure_database' => ! empty($options['wants_database'])
                ? $this->databases->ensure($site, $this->pool((int) $options['database_pool_id']))
                : null,
            'ensure_storage_path' => (! empty($options['wants_storage']) || ! empty($options['wants_sftp']))
                ? $this->rememberStoragePath($site)
                : null,
            'ensure_sftp_user' => ! empty($options['wants_sftp'])
                ? $this->sftp->ensure($site, $this->pool((int) $options['storage_pool_id']))
                : null,
            'write_env' => $this->environment->persist($site->refresh()),
            'deploy_application' => $this->deploy($site->refresh()),
            'attach_domain_ssl' => $this->attachDomain($site->refresh()),
            default => null,
        };
    }

    private function rememberStoragePath(Site $site): void
    {
        $options = $site->options ?? [];
        $options['storage_path'] = '/var/sites/'.$site->id;
        $site->options = $options;
        $site->save();
    }

    private function deploy(Site $site): void
    {
        $env = $site->environment ?? $this->environment->persist($site);
        $existingId = $site->dokploy_app_id;
        $result = $this->driver->deployApplication([
            'name' => $site->domain,
            'app_name' => 'site-'.$site->id,
            'domain' => $site->domain,
            'recipe' => $site->recipe?->slug,
            'repository' => $site->repository,
            'branch' => $site->options['git_branch'] ?? 'main',
            'env' => $this->environment->render($env),
            'environment_id' => config('dockhost.dokploy.environment_id'),
            'application_id' => is_string($existingId) && $existingId !== '' && ! str_starts_with($existingId, 'local_')
                ? $existingId
                : null,
        ]);

        $site->dokploy_app_id = $result['external_id'] ?? $site->dokploy_app_id;
        $meta = $site->meta ?? [];

        if (! empty($result['project_id'])) {
            $meta['dokploy_project_id'] = $result['project_id'];
        }

        if (! empty($result['environment_id'])) {
            $meta['dokploy_environment_id'] = $result['environment_id'];
        }

        $site->meta = $meta;
        $site->save();
    }

    public function refreshDeployStatus(Site $site): Site
    {
        $applicationId = (string) $site->dokploy_app_id;

        if ($applicationId === '') {
            return $site;
        }

        try {
            $result = $this->driver->applicationStatus($applicationId);
        } catch (Throwable $exception) {
            $site->status = $site->status === 'suspended' ? 'suspended' : 'provisioning';
            $site->last_error = OperatorError::present($exception->getMessage());
            $site->save();

            return $site;
        }
        $meta = $site->meta ?? [];

        if (! empty($result['project_id'])) {
            $meta['dokploy_project_id'] = $result['project_id'];
        }

        if (! empty($result['environment_id'])) {
            $meta['dokploy_environment_id'] = $result['environment_id'];
        }

        $site->meta = $meta;
        $remote = $result['status'] ?? null;

        if (in_array($remote, ['done', 'running'], true)) {
            $site->status = 'active';
            $site->last_error = null;
        } elseif ($remote === 'error') {
            $site->status = 'provisioning';
            $site->last_error = 'Dokploy reported an error for this application.';
        } elseif ($site->status !== 'suspended') {
            $site->status = 'provisioning';
        }

        $site->save();

        return $site;
    }

    private function awaitsDokploy(Site $site): bool
    {
        return is_string($site->dokploy_app_id)
            && $site->dokploy_app_id !== ''
            && ! str_starts_with($site->dokploy_app_id, 'local_');
    }

    private function attachDomain(Site $site): void
    {
        $created = $this->driver->attachDomain([
            'domain' => $site->domain,
            'application_id' => $site->dokploy_app_id,
            'port' => $site->recipe?->stack === 'node' ? 3000 : 80,
        ]);

        $raw = is_array($created['raw'] ?? null) ? $created['raw'] : [];
        $domainId = $raw['domainId'] ?? $raw['id'] ?? null;

        SiteDomain::query()->updateOrCreate(
            ['host' => strtolower($site->domain)],
            [
                'site_id' => $site->id,
                'primary' => true,
                'dokploy_domain_id' => is_string($domainId) ? $domainId : null,
            ],
        );
    }

    private function pool(int $id): Pool
    {
        return Pool::query()->findOrFail($id);
    }
}
