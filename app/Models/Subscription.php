<?php


// Subscription.php — Subscription module.
//
// exports: Subscription | attr:Fillable | Subscription::customer(): BelongsTo | Subscription::servicePlan(): BelongsTo | Subscription::domains(): HasMany | Subscription::canAddDomain(): bool
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/SubscriptionController.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/DomainFactory.php
//         database/factories/SubscriptionFactory.php
//         tests/Feature/CustomerTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
//         tests/Feature/SubscriptionTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'service_plan_id', 'name', 'status'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SubscriptionStatus::Active->value,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function canAddDomain(): bool
    {
        $maxDomains = $this->servicePlan?->max_domains;

        return $maxDomains === null || $this->domains()->count() < $maxDomains;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
        ];
    }
}
