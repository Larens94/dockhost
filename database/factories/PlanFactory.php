<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Starter',
            'slug' => fake()->unique()->slug(2),
            'stripe_price_id' => 'price_starter_stub',
            'amount_cents' => 1900,
            'currency' => 'eur',
            'interval' => 'month',
            'site_quota' => 3,
            'features' => ['SFTP'],
            'entitlements' => [
                'sftp' => true,
                'cache' => true,
                'dedicated_database' => false,
            ],
            'active' => true,
        ];
    }
}
