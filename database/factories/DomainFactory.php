<?php


// DomainFactory.php — DomainFactory module.
//
// exports: DomainFactory | DomainFactory::definition(): array | DomainFactory::laravel(): static | DomainFactory::forCustomer(Customer $customer): static
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Enums\DomainStack;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'customer_id' => fn (array $attributes): int => Subscription::query()->findOrFail($attributes['subscription_id'])->customer_id,
            'fqdn' => fake()->unique()->domainName(),
            'infrastructure_id' => Infrastructure::factory(),
            'infra_slug' => fn (array $attributes): string => Infrastructure::query()->findOrFail($attributes['infrastructure_id'])->slug,
            'stack' => DomainStack::None,
        ];
    }

    public function laravel(): static
    {
        return $this->state(fn (): array => [
            'stack' => DomainStack::Laravel,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state(fn (): array => [
            'customer_id' => $customer->id,
            'subscription_id' => Subscription::factory()->state([
                'customer_id' => $customer->id,
            ]),
        ]);
    }
}
