<?php


// SftpUserFactory.php — SftpUserFactory module.
//
// exports: SftpUserFactory | SftpUserFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\Domain;
use App\Models\SftpUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SftpUser>
 */
class SftpUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fqdn = fake()->domainName();

        return [
            'domain_id' => Domain::factory(),
            'infrastructure_id' => fn (array $attributes): ?int => Domain::query()->find($attributes['domain_id'])?->infrastructure_id,
            'username' => 'sftp_'.fake()->unique()->lexify('??????'),
            'password_encrypted' => 'secret',
            'home_path' => '/data/customer/'.$fqdn,
        ];
    }
}
