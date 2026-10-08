<?php


// ServicePlanFactory.php — ServicePlanFactory module.
//
// exports: ServicePlanFactory | ServicePlanFactory::definition(): array | ServicePlanFactory::unlimited(): static
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\ServicePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServicePlan>
 */
class ServicePlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'max_domains' => null,
            'disk_mb' => null,
        ];
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Unlimited',
            'slug' => 'unlimited',
            'max_domains' => null,
            'disk_mb' => null,
        ]);
    }
}
