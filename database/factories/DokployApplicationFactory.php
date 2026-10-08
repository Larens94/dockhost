<?php


// DokployApplicationFactory.php — DokployApplicationFactory module.
//
// exports: DokployApplicationFactory | DokployApplicationFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\DokployApplication;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DokployApplication>
 */
class DokployApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'dokploy_application_id' => null,
            'dokploy_environment_id' => null,
            'git_url' => fake()->optional()->url(),
        ];
    }
}
