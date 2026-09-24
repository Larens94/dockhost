<?php

namespace Database\Factories;

use App\Models\Pool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pool>
 */
class PoolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->bothify('pool-????'),
            'kind' => 'database',
            'engine' => 'mariadb',
            'capacity' => 10,
            'usage' => 0,
            'meta' => ['host' => '10.0.0.10', 'port' => 3306, 'mode' => 'shared'],
        ];
    }
}
