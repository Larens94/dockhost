<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Plan;
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
            'client_id' => Client::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'stripe_subscription_id' => 'sub_stub_'.fake()->unique()->bothify('??????'),
            'current_period_end' => now()->addMonth(),
            'meta' => ['mode' => 'stub'],
        ];
    }
}
