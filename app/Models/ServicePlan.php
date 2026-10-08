<?php


// ServicePlan.php — ServicePlan module.
//
// exports: ServicePlan | attr:Fillable | ServicePlan::subscriptions(): HasMany
// used_by: app/Http/Controllers/CustomerController.php
//         app/Http/Controllers/ServicePlanController.php
//         app/Http/Controllers/SubscriptionController.php
//         database/factories/ServicePlanFactory.php
//         database/factories/SubscriptionFactory.php
//         database/seeders/ServicePlanSeeder.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/ServicePlanTest.php
//         tests/Feature/SubscriptionTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use Database\Factories\ServicePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'max_domains', 'disk_mb'])]
class ServicePlan extends Model
{
    /** @use HasFactory<ServicePlanFactory> */
    use HasFactory;

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_domains' => 'integer',
            'disk_mb' => 'integer',
        ];
    }
}
