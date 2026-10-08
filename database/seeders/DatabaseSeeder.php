<?php

// DatabaseSeeder.php — DatabaseSeeder module.
//
// exports: DatabaseSeeder | DatabaseSeeder::run(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServicePlanSeeder::class);

        $email = env('ADMIN_EMAIL', 'test@example.com');
        $password = env('ADMIN_PASSWORD');

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => $password ?: 'password',
                'is_admin' => true,
            ],
        );
    }
}
