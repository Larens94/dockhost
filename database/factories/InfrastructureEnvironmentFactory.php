<?php

// InfrastructureEnvironmentFactory.php — Factory for InfrastructureEnvironment.
//
// exports: InfrastructureEnvironmentFactory | InfrastructureEnvironmentFactory::definition(): array | InfrastructureEnvironmentFactory::production(): static | InfrastructureEnvironmentFactory::staging(): static
// used_by: tests
// rules:   production() sets is_default; staging() never is_default.
// agent:   composer-2.5-fast | cursor | 2026-10-11 | s_infra_env | InfrastructureEnvironment factory
// message:

namespace Database\Factories;

use App\Models\Infrastructure;
use App\Models\InfrastructureEnvironment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InfrastructureEnvironment>
 */
class InfrastructureEnvironmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(1);

        return [
            'infrastructure_id' => Infrastructure::factory(),
            'name' => $name,
            'slug' => $name,
            'dokploy_environment_id' => 'env-'.fake()->unique()->numerify('####'),
            'dokploy_compose_id' => null,
            'is_default' => false,
        ];
    }

    public function production(): static
    {
        return $this->state(fn (): array => [
            'name' => 'production',
            'slug' => 'production',
            'is_default' => true,
        ]);
    }

    public function staging(): static
    {
        return $this->state(fn (): array => [
            'name' => 'staging',
            'slug' => 'staging',
            'is_default' => false,
        ]);
    }
}
