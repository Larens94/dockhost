<?php

// DokployDestinationPresentation.php — Redact Dokploy S3 destinations for the panel UI.
//
// exports: DokployDestinationPresentation | DokployDestinationPresentation::forSelect(array $destinations): array
// used_by: app/Http/Controllers/InfrastructureController.php
//         app/Http/Controllers/DomainController.php
// rules:   Never expose accessKey, secretAccessKey, or additionalFlags secrets to Inertia.
// agent:   composer-2.5-fast | cursor | 2026-10-21 | s_dokploy_domain_backup | Destination list for infra UI
// message:

namespace App\Support;

class DokployDestinationPresentation
{
    /**
     * @param  list<array<string, mixed>>  $destinations
     * @return list<array{id: string, label: string, bucket: string|null, provider: string|null}>
     */
    public static function forSelect(array $destinations): array
    {
        $rows = [];

        foreach ($destinations as $destination) {
            $id = $destination['destinationId'] ?? $destination['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            $name = is_string($destination['name'] ?? null) ? $destination['name'] : $id;
            $bucket = is_string($destination['bucket'] ?? null) ? $destination['bucket'] : null;
            $provider = is_string($destination['provider'] ?? null) ? $destination['provider'] : null;
            $label = $bucket !== null && $bucket !== '' ? "{$name} ({$bucket})" : $name;

            $rows[] = [
                'id' => $id,
                'label' => $label,
                'bucket' => $bucket,
                'provider' => $provider,
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return $rows;
    }
}
