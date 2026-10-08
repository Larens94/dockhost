<?php

// InfraDataNetworks.php — UNUSED helper for {slug}-db / {slug}-storage overlays.
//
// exports: InfraDataNetworks | InfraDataNetworks::ensure(string $slug): array | InfraDataNetworks::attachApplicationToDatabase(string $applicationId, string $slug): void
// used_by: tests/Unit/InfraDataNetworksTest.php only (NOT InfrastructureProvisioner / DokployApplicationAttacher)
// rules:   DO NOT call from provision or attach paths. Shipped model is one compose on dokploy-network.
//          Creating {slug}-db/{slug}-storage is abandoned — isolated-network split was a misunderstanding.
//          Keep class for unit docs / possible emergency use; never wire it back without explicit product decision.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Mark unused; provisioners must not call
// message:

namespace App\Services\Infra;

use App\Services\Dokploy\DokployClient;

/**
 * UNUSED: would create {slug}-db / {slug}-storage. Provisioners must stay on dokploy-network only.
 */
class InfraDataNetworks
{
    public function __construct(private DokployClient $dokploy) {}

    /**
     * @return array{db: string, storage: string}
     */
    public function ensure(string $slug): array
    {
        $wanted = [
            'db' => $slug.'-db',
            'storage' => $slug.'-storage',
        ];
        $existing = $this->idsByName();
        $ids = [];

        foreach ($wanted as $role => $name) {
            if (isset($existing[$name])) {
                $ids[$role] = $existing[$name];

                continue;
            }

            $created = $this->dokploy->createNetwork([
                'name' => $name,
                'driver' => 'overlay',
                'attachable' => true,
            ]);
            $ids[$role] = $this->dokploy->idFrom($created, 'networkId');
        }

        $this->attachApplication((string) config('dokploy.self_application_id'), array_values($ids));

        return $ids;
    }

    public function attachApplicationToDatabase(string $applicationId, string $slug): void
    {
        $networkId = $this->idsByName()[$slug.'-db'] ?? null;

        if (! is_string($networkId) || $networkId === '') {
            return;
        }

        $this->attachApplication($applicationId, [$networkId]);
    }

    /**
     * @param  list<string>  $networkIds
     */
    private function attachApplication(string $applicationId, array $networkIds): void
    {
        if ($applicationId === '' || $networkIds === []) {
            return;
        }

        $current = $this->dokploy->getApplication($applicationId);
        $existing = $current['networkIds'] ?? [];
        $existingIds = is_array($existing)
            ? array_values(array_filter($existing, is_string(...)))
            : [];
        $merged = array_values(array_unique([...$existingIds, ...$networkIds]));

        if ($merged === $existingIds) {
            return;
        }

        $this->dokploy->updateApplication([
            'applicationId' => $applicationId,
            'networkIds' => $merged,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function idsByName(): array
    {
        $indexed = [];

        foreach ($this->dokploy->listNetworks() as $network) {
            $name = $network['name'] ?? null;
            $id = $network['networkId'] ?? null;

            if (is_string($name) && $name !== '' && is_string($id) && $id !== '') {
                $indexed[$name] = $id;
            }
        }

        return $indexed;
    }
}
