<?php

namespace App\Services;

use App\Contracts\InfrastructureDriver;
use App\Models\Client;
use App\Models\Site;

class SiteSuspension
{
    public function __construct(
        private InfrastructureDriver $driver,
        private AuditLogger $audit,
    ) {}

    public function apply(Client $client): void
    {
        $client->refresh();

        if ($this->shouldStop($client)) {
            $this->stopSites($client);

            return;
        }

        if ($client->status === 'active') {
            $this->startSites($client);
        }
    }

    private function shouldStop(Client $client): bool
    {
        return in_array($client->status, ['suspended', 'archived'], true)
            || $client->billing_status === 'past_due';
    }

    private function stopSites(Client $client): void
    {
        $client->sites()
            ->whereIn('status', ['active', 'provisioning'])
            ->get()
            ->each(fn (Site $site) => $this->stop($site));
    }

    private function startSites(Client $client): void
    {
        $client->sites()
            ->where('status', 'suspended')
            ->get()
            ->each(fn (Site $site) => $this->start($site));
    }

    private function stop(Site $site): void
    {
        if ($site->dokploy_app_id) {
            $this->driver->stopApplication((string) $site->dokploy_app_id);
        }

        $meta = $site->meta ?? [];
        $meta['status_before_suspend'] = $site->status;
        $site->meta = $meta;
        $site->status = 'suspended';
        $site->save();
        $this->audit->log('site.suspended', $site, ['domain' => $site->domain]);
    }

    private function start(Site $site): void
    {
        if ($site->dokploy_app_id) {
            $this->driver->startApplication((string) $site->dokploy_app_id);
        }

        $previous = $site->meta['status_before_suspend'] ?? 'active';
        $site->status = in_array($previous, ['active', 'provisioning'], true) ? $previous : 'active';
        $site->save();
        $this->audit->log('site.started', $site, ['domain' => $site->domain]);
    }
}
