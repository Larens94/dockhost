<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'active',
            'billing_email' => fake()->unique()->safeEmail(),
            'billing_status' => 'active',
        ];
    }
}
