<?php


// StorageShare.php — StorageShare module.
//
// exports: StorageShare | attr:Fillable | StorageShare::domain(): BelongsTo
// used_by: app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         database/factories/StorageShareFactory.php
//         tests/Feature/AccessAccountTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use Database\Factories\StorageShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['domain_id', 'path'])]
class StorageShare extends Model
{
    /** @use HasFactory<StorageShareFactory> */
    use HasFactory;

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
