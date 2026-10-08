<?php


// ServicePlanSeeder.php — ServicePlanSeeder module.
//
// exports: ServicePlanSeeder | ServicePlanSeeder::run(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Seeders;

use App\Models\ServicePlan;
use Illuminate\Database\Seeder;

class ServicePlanSeeder extends Seeder
{
    public function run(): void
    {
        ServicePlan::query()->updateOrCreate(
            ['slug' => 'unlimited'],
            [
                'name' => 'Unlimited',
                'max_domains' => null,
                'disk_mb' => null,
            ],
        );
    }
}
