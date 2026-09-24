<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Site;
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

            $site->status = 'active';
            $site->save();
            $this->audit->log('site.provisioned', $site, ['domain' => $site->domain]);
        } catch (Throwable $exception) {
            $this->ledger->release($site);
            $site->status = 'failed';
            $site->last_error = $exception->getMessage();
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
        $result = $this->driver->deployApplication([
            'name' => $site->domain,
            'app_name' => 'site-'.$site->id,
            'domain' => $site->domain,
            'recipe' => $site->recipe?->slug,
            'repository' => $site->repository,
            'env' => $this->environment->render($env),
            'environment_id' => config('dockhost.dokploy.environment_id'),
        ]);

        $site->dokploy_app_id = $result['external_id'] ?? null;
        $site->save();
    }

    private function attachDomain(Site $site): void
    {
        $this->driver->attachDomain([
            'domain' => $site->domain,
            'application_id' => $site->dokploy_app_id,
            'port' => $site->recipe?->stack === 'node' ? 3000 : 80,
        ]);
    }

    private function pool(int $id): Pool
    {
        return Pool::query()->findOrFail($id);
    }
}
