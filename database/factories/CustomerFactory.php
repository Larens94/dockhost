<?php


// CustomerFactory.php — CustomerFactory module.
//
// exports: CustomerFactory | CustomerFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->optional()->companyEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
