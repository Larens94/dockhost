<?php

// User.php — User module.
//
// exports: User | attr:Fillable | attr:Hidden
// used_by: config/auth.php
//         database/factories/UserFactory.php
//         database/seeders/DatabaseSeeder.php
//         tests/Feature/AccessAccountTest.php
//         tests/Feature/AttachOptionalStackDomainsTest.php
//         tests/Feature/AttachPhpmyadminDomainTest.php
//         tests/Feature/AuthenticationTest.php
//         tests/Feature/CustomerTest.php
//         tests/Feature/DestroyInfrastructureTest.php
//         tests/Feature/DokployInspectTest.php
//         tests/Feature/DomainLifecycleTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/InfrastructureAssignmentTest.php
//         tests/Feature/InfrastructureStackUpdateTest.php
//         tests/Feature/LaravelBootEnvAlignTest.php
//         tests/Feature/LaravelToolkitTest.php
//         tests/Feature/RecreateMysqlDatadirTest.php
//         tests/Feature/ServicePlanTest.php
//         tests/Feature/SubscriptionTest.php
// rules:   is_admin=true bypasses domain_user pivot — full panel. Members MUST have domain_user rows only.
//          Never grant is_admin via domain IAM — owner/developer/readonly on pivot only.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | is_admin + domains() pivot for hosting-scoped access.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | domainMemberRole + mutate/IAM helpers.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa | Optional TOTP + recovery codes.

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\DomainMemberRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['name', 'email', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_recovery_codes' => 'array',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && filled($this->two_factor_secret);
    }

    public function twoFactorSecretPlain(): string
    {
        return Crypt::decryptString((string) $this->two_factor_secret);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class)
            ->withTimestamps()
            ->withPivot(['dokploy_member_id', 'role']);
    }

    public function canAccessDomain(Domain|int $domain): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $domainId = $domain instanceof Domain ? $domain->getKey() : $domain;

        return $this->domains()->whereKey($domainId)->exists();
    }

    public function domainMemberRole(Domain|int $domain): ?DomainMemberRole
    {
        if ($this->isAdmin()) {
            return null;
        }

        $domainId = $domain instanceof Domain ? $domain->getKey() : $domain;
        $pivotRole = $this->domains()->whereKey($domainId)->value('role');

        if (! is_string($pivotRole) || $pivotRole === '') {
            return $this->canAccessDomain($domainId) ? DomainMemberRole::Developer : null;
        }

        return DomainMemberRole::tryFrom($pivotRole) ?? DomainMemberRole::Developer;
    }

    public function canManageDomainMembers(Domain $domain): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->domainMemberRole($domain)?->canManageMembers() ?? false;
    }

    public function canMutateDomainHosting(Domain $domain): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->domainMemberRole($domain)?->canMutateHosting() ?? false;
    }
}
