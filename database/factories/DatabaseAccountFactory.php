<?php


// DatabaseAccountFactory.php — DatabaseAccountFactory module.
//
// exports: DatabaseAccountFactory | DatabaseAccountFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use App\Models\DatabaseAccount;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatabaseAccount>
 */
class DatabaseAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => Domain::factory(),
            'infrastructure_id' => fn (array $attributes): ?int => Domain::query()->find($attributes['domain_id'])?->infrastructure_id,
            'engine' => DatabaseEngine::Mysql,
            'infra_slug' => 'infra1',
            'host' => 'infra1-mariadb',
            'port' => 3306,
            'database_name' => 'd_'.fake()->unique()->lexify('??????'),
            'username' => 'u_'.fake()->unique()->lexify('??????'),
            'privilege' => DatabasePrivilege::All,
            'password_encrypted' => 'secret',
        ];
    }
}
