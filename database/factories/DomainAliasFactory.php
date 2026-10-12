<?php

// DomainAliasFactory.php — Factory for DomainAlias.
//
// exports: DomainAliasFactory
// used_by: tests/Feature/DomainAliasTest.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_domain_alias | Alias factory
// message:

namespace Database\Factories;

use App\Enums\DomainAliasKind;
use App\Models\Domain;
use App\Models\DomainAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainAlias>
 */
class DomainAliasFactory extends Factory
{
    protected $model = DomainAlias::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'fqdn' => fake()->unique()->domainName(),
            'kind' => DomainAliasKind::Alias,
            'web_service' => true,
            'redirect_301' => false,
            'mail_service' => false,
            'sync_dns' => false,
            'dokploy_domain_id' => null,
        ];
    }

    public function subdomain(): static
    {
        return $this->state(fn (): array => [
            'kind' => DomainAliasKind::Subdomain,
        ]);
    }
}
