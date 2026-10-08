<?php


// DokployApplication.php — DokployApplication module.
//
// exports: DokployApplication | attr:Fillable | DokployApplication::domain(): BelongsTo
// used_by: app/Services/Hosting/DomainProvisioner.php
//         database/factories/DokployApplicationFactory.php
//         tests/Feature/DomainLifecycleTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/DomainUiTest.php
//         tests/Feature/LaravelBootEnvAlignTest.php
//         tests/Feature/LaravelToolkitTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use Database\Factories\DokployApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'domain_id',
    'dokploy_application_id',
    'dokploy_environment_id',
    'git_url',
])]
class DokployApplication extends Model
{
    /** @use HasFactory<DokployApplicationFactory> */
    use HasFactory;

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
