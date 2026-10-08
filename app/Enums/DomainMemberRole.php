<?php

// DomainMemberRole.php — Panel role on domain_user pivot (owner | developer | readonly).
//
// exports: DomainMemberRole | DomainMemberRole::Owner | DomainMemberRole::Developer | DomainMemberRole::Readonly | DomainMemberRole::label(): string
// used_by: app/Models/User.php
//         app/Models/Domain.php
//         app/Http/Controllers/DomainMemberController.php
// rules:   owner = IAM on domain + developer powers; developer = mutate hosting; readonly = view credentials only.
//          Existing pivot rows default developer — admin should assign owner where needed.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Enum for domain member IAM roles.

namespace App\Enums;

enum DomainMemberRole: string
{
    case Owner = 'owner';
    case Developer = 'developer';
    case Readonly = 'readonly';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietario',
            self::Developer => 'Sviluppatore',
            self::Readonly => 'Sola lettura',
        };
    }

    public function canMutateHosting(): bool
    {
        return $this === self::Owner || $this === self::Developer;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
