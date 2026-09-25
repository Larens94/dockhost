<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Contracts\RuntimeAdmin;
use App\Models\Pool;
use App\Models\Site;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SiteLifecycle
{
    public function __construct(
        private InfrastructureDriver $driver,
        private TenantDatabaseProvisioner $databases,
        private PoolLedger $ledger,
        private AuditLogger $audit,
        private RuntimeAdmin $runtime,
    ) {}

    public function deprovision(Site $site): void
    {
        $poolIds = $this->ledger->poolIds($site);
        $this->releaseDokployResources($site);

        $result = $this->driver->destroyApplication([
            'application_id' => $site->dokploy_app_id,
            'domain' => $site->domain,
        ]);

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException($result['message'] ?? 'Dokploy refused to delete the application.');
        }

        $site->loadMissing('databaseAccount');

        if ($site->databaseAccount) {
            $this->databases->drop($site->databaseAccount);
        }

        $domain = $site->domain;
        $siteId = $site->id;
        $site->delete();
        $this->ledger->recalculate($poolIds);
        $this->audit->log('site.deprovisioned', null, [
            'domain' => $domain,
            'site_id' => $siteId,
        ]);
    }

    private function releaseDokployResources(Site $site): void
    {
        $site->loadMissing('domains');

        foreach ($site->domains as $domain) {
            if (! is_string($domain->dokploy_domain_id) || $domain->dokploy_domain_id === '') {
                continue;
            }

            try {
                $this->driver->deleteDomain($domain->dokploy_domain_id);
            } catch (Throwable $exception) {
                Log::warning('Dokploy domain delete failed', [
                    'domain' => $domain->host,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($site->meta['services'] ?? [] as $kind => $service) {
            $id = is_array($service) ? ($service['external_id'] ?? null) : null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            try {
                $this->driver->removeService((string) $kind, $id);
            } catch (Throwable $exception) {
                Log::warning('Dokploy service delete failed', [
                    'kind' => $kind,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->releaseSftpUser($site);
    }

    private function releaseSftpUser(Site $site): void
    {
        $site->loadMissing('sftpAccount');
        $account = $site->sftpAccount;

        if (! $account || $account->status !== 'provisioned') {
            return;
        }

        $poolId = $site->options['storage_pool_id'] ?? null;
        $pool = $poolId ? Pool::query()->find($poolId) : null;

        if (! $pool || ! $this->runtime->canManage($pool)) {
            return;
        }

        try {
            $this->runtime->deleteSftpUser($pool, $account->username);
        } catch (Throwable $exception) {
            Log::warning('SFTP user delete failed', [
                'username' => $account->username,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
