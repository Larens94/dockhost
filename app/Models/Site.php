<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'recipe_id',
        'domain',
        'repository',
        'status',
        'dokploy_app_id',
        'pool_ids',
        'meta',
        'options',
        'toolkit_state',
        'environment',
        'service_secrets',
        'last_error',
        'usage_held',
    ];

    protected function casts(): array
    {
        return [
            'pool_ids' => 'array',
            'meta' => 'array',
            'options' => 'array',
            'toolkit_state' => 'array',
            'environment' => 'encrypted:array',
            'service_secrets' => 'encrypted:array',
            'usage_held' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(Pool::class, 'site_pools')
            ->withPivot('purpose')
            ->withTimestamps();
    }

    public function databaseAccount(): HasOne
    {
        return $this->hasOne(SiteDatabase::class);
    }

    public function sftpAccount(): HasOne
    {
        return $this->hasOne(SiteSftpAccount::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(SiteDomain::class);
    }
}
