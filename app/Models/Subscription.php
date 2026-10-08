<?php

// Subscription.php — Subscription module.
//
// exports: Subscription | attr:Fillable | Subscription::customer(): BelongsTo | Subscription::servicePlan(): BelongsTo | Subscription::domains(): HasMany | Subscription::canAddDomain(): bool | Subscription::deletionConfirmation(): string | Subscription::deletionTargets(): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/SubscriptionController.php
//         app/Http/Requests/DestroySubscriptionRequest.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/DomainFactory.php
//         database/factories/SubscriptionFactory.php
//         tests/Feature/CustomerTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
//         tests/Feature/SubscriptionTest.php
// rules:   Deleting a space decommissions every linked domain (Dokploy application included) before the subscription row goes away.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Confirmation phrase and Dokploy targets for space delete.
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
     * GitHub-style delete check: the only domain FQDN, or the space name when there isn't exactly one.
     */
    public function deletionConfirmation(): string
    {
        $this->loadMissing('domains');

        if ($this->domains->count() === 1) {
            return (string) $this->domains->first()->fqdn;
        }

        return (string) $this->name;
    }

    /**
     * Domains and Dokploy applications removed with this space.
     *
     * @return list<array{fqdn: string, stack_label: string, infra_slug: ?string, dokploy_service: ?string}>
     */
    public function deletionTargets(): array
    {
        $this->loadMissing('domains.dokployApplication');

        return $this->domains
            ->sortBy(fn (Domain $domain): string => $domain->fqdn.'-'.$domain->id)
            ->map(function (Domain $domain): array {
                $applicationId = $domain->dokployApplication?->dokploy_application_id;

                return [
                    'fqdn' => $domain->fqdn,
                    'stack_label' => $domain->stack_label,
                    'infra_slug' => $domain->infra_slug,
                    'dokploy_service' => is_string($applicationId) && $applicationId !== '' ? $domain->fqdn : null,
                ];
            })
            ->values()
            ->all();
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
