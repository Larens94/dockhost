<?php


// SftpUser.php — SftpUser module.
//
// exports: SftpUser | attr:Fillable | attr:Hidden | SftpUser::domain(): BelongsTo | SftpUser::infrastructure(): BelongsTo | SftpUser::revealPassword(): static
// used_by: app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         app/Services/Infra/SftpDaemonSync.php
//         database/factories/SftpUserFactory.php
//         tests/Feature/AccessAccountTest.php
//         tests/Feature/DomainLifecycleTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Unit/SftpDaemonSyncTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use Database\Factories\SftpUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['domain_id', 'infrastructure_id', 'username', 'password_encrypted', 'home_path'])]
#[Hidden(['password_encrypted'])]
class SftpUser extends Model
{
    /** @use HasFactory<SftpUserFactory> */
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
            'password_encrypted' => 'encrypted',
        ];
    }
}
