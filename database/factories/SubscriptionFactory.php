<?php


// SubscriptionFactory.php — SubscriptionFactory module.
//
// exports: SubscriptionFactory | SubscriptionFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\ServicePlan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'service_plan_id' => ServicePlan::factory(),
            'name' => fake()->unique()->domainName(),
            'status' => SubscriptionStatus::Active,
        ];
    }
}
