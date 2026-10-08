<?php


// StorageShareFactory.php — StorageShareFactory module.
//
// exports: StorageShareFactory | StorageShareFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\Domain;
use App\Models\StorageShare;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StorageShare>
 */
class StorageShareFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fqdn = fake()->domainName();

        return [
            'domain_id' => Domain::factory(),
            'path' => '/data/customer/'.$fqdn,
        ];
    }
}
