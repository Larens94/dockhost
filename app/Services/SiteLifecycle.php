<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Site;
use RuntimeException;

class SiteLifecycle
{
    public function __construct(
        private InfrastructureDriver $driver,
        private TenantDatabaseProvisioner $databases,
        private PoolLedger $ledger,
        private AuditLogger $audit,
    ) {}

    public function deprovision(Site $site): void
    {
        $poolIds = $this->ledger->poolIds($site);
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
}
