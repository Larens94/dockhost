<?php

// DomainBackupFactory.php — Factory for DomainBackup records.
//
// exports: DomainBackupFactory | DomainBackupFactory::definition(): array | DomainBackupFactory::completed(): static | DomainBackupFactory::failed(): static
// used_by: tests
// rules:   Prefer completed() for restore/align tests; never put real dump contents here.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_domain_backup | DomainBackup factory
// message:

namespace Database\Factories;

use App\Models\Domain;
use App\Models\DomainBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainBackup>
 */
class DomainBackupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'infrastructure_environment_id' => null,
            'kind' => 'database',
            'status' => 'pending',
            'disk_path' => null,
            'bytes' => null,
            'error' => null,
            'meta' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'disk_path' => 'backups/domains/1/test.sql',
            'bytes' => 128,
            'error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'error' => 'dump failed',
        ]);
    }
}
