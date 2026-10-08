<?php


// DatabaseAccount.php — DatabaseAccount module.
//
// exports: DatabaseAccount | attr:Fillable | attr:Hidden | DatabaseAccount::domain(): BelongsTo | DatabaseAccount::infrastructure(): BelongsTo | DatabaseAccount::revealPassword(): static
// used_by: app/Http/Requests/StoreInfrastructureDatabaseUserRequest.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/DatabaseAccountFactory.php
//         tests/Feature/AccessAccountTest.php
//         tests/Feature/DomainUiTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use App\Enums\DatabaseEngine;
use App\Enums\DatabasePrivilege;
use Database\Factories\DatabaseAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'domain_id',
    'infrastructure_id',
    'engine',
    'infra_slug',
    'host',
    'port',
    'database_name',
    'username',
    'privilege',
    'password_encrypted',
])]
#[Hidden(['password_encrypted'])]
class DatabaseAccount extends Model
{
    /** @use HasFactory<DatabaseAccountFactory> */
    use HasFactory;

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(Infrastructure::class);
    }

    public function revealPassword(): static
    {
        $this->setAttribute('password', $this->password_encrypted);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'engine' => DatabaseEngine::class,
            'privilege' => DatabasePrivilege::class,
            'port' => 'integer',
            'password_encrypted' => 'encrypted',
        ];
    }
}
