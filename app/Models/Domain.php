<?php

// Domain.php — Domain module.
//
// exports: Domain | attr:Fillable | Domain::customer(): BelongsTo | Domain::infrastructure(): BelongsTo | Domain::subscription(): BelongsTo | Domain::databaseAccounts(): HasMany | Domain::storageShares(): HasMany | Domain::sftpUsers(): HasMany | Domain::dokployApplication(): HasOne
// used_by: app/Console/Commands/AlignMysqlFromComposeCommand.php
//         app/Http/Controllers/DomainController.php
//         app/Http/Controllers/LaravelToolkitController.php
//         app/Http/Requests/StoreDomainDatabaseRequest.php
//         app/Http/Requests/StoreInfrastructureSftpUserRequest.php
//         app/Http/Requests/UpdateDomainRequest.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         app/Services/Laravel/LaravelToolkitExecutor.php
//         database/factories/DatabaseAccountFactory.php
//         database/factories/DokployApplicationFactory.php
//         database/factories/DomainFactory.php
//         database/factories/SftpUserFactory.php
//         database/factories/StorageShareFactory.php
//         tests/Feature/AccessAccountTest.php
//         tests/Feature/DestroyInfrastructureTest.php
//         tests/Feature/DomainLifecycleTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
//         tests/Feature/LaravelBootEnvAlignTest.php
//         tests/Feature/LaravelToolkitTest.php
// rules:   owner count on pivot — DomainMemberController MUST NOT remove/demote last owner.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | pivot role + ownerCount().
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | php_settings JSON for Dokploy env.

namespace App\Models;

use App\Enums\DomainMemberRole;
use App\Enums\DomainStack;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['customer_id', 'subscription_id', 'infrastructure_id', 'fqdn', 'infra_slug', 'stack', 'php_settings'])]
class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'dokploy_application_url',
        'stack_label',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stack' => DomainStack::class,
            'php_settings' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function databaseAccounts(): HasMany
    {
        return $this->hasMany(DatabaseAccount::class);
    }

    public function storageShares(): HasMany
    {
        return $this->hasMany(StorageShare::class);
    }

    public function sftpUsers(): HasMany
    {
        return $this->hasMany(SftpUser::class);
    }

    public function dokployApplication(): HasOne
    {
        return $this->hasOne(DokployApplication::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot(['dokploy_member_id', 'role']);
    }

    public function ownerCount(): int
    {
        return $this->members()->wherePivot('role', DomainMemberRole::Owner->value)->count();
    }

    /**
     * Application dashboard in the infrastructure project/environment, not the panel env.
     *
     * @return Attribute<?string, never>
     */
    protected function dokployApplicationUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->relationLoaded('dokployApplication') || ! $this->relationLoaded('infrastructure')) {
                return null;
            }

            $applicationId = $this->dokployApplication?->dokploy_application_id;

            return $this->infrastructure?->dokployApplicationUrl(
                is_string($applicationId) ? $applicationId : null,
            );
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function stackLabel(): Attribute
    {
        return Attribute::get(fn (): string => ($this->stack ?? DomainStack::None)->label());
    }
}
