<?php

// DomainStack.php — DomainStack module.
//
// exports: DomainStack | DomainStack::None | DomainStack::Static | DomainStack::Php | DomainStack::Laravel | DomainStack::Node | DomainStack::Python | DomainStack::Go | DomainStack::label(): string | DomainStack::createsApplication(): bool | DomainStack::isLaravel(): bool | DomainStack::usesLaravelEnv(): bool | DomainStack::usesPhpIniAtRuntime(): bool | DomainStack::mark(): string | DomainStack::choices(): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Http/Controllers/LaravelToolkitController.php
//         app/Http/Requests/StoreDomainRequest.php
//         app/Models/Domain.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/DomainFactory.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Enums;

enum DomainStack: string
{
    case None = 'none';
    case Static = 'static';
    case Php = 'php';
    case Laravel = 'laravel';
    case Node = 'node';
    case Python = 'python';
    case Go = 'go';

    public function label(): string
    {
        return __('panel.stacks.'.$this->value);
    }

    public function createsApplication(): bool
    {
        return $this !== self::None;
    }

    public function isLaravel(): bool
    {
        return $this === self::Laravel;
    }

    public function usesLaravelEnv(): bool
    {
        return $this === self::Laravel;
    }

    public function usesPhpIniAtRuntime(): bool
    {
        return $this === self::Laravel || $this === self::Php;
    }

    public function mark(): string
    {
        return match ($this) {
            self::None => '∅',
            self::Static => 'HTML',
            self::Php => 'PHP',
            self::Laravel => 'L',
            self::Node => 'JS',
            self::Python => 'Py',
            self::Go => 'Go',
        };
    }

    /**
     * @return list<self>
     */
    public static function choices(): array
    {
        return [
            self::None,
            self::Static,
            self::Php,
            self::Laravel,
            self::Node,
            self::Python,
            self::Go,
        ];
    }
}
