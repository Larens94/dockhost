<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    protected $fillable = [
        'client_id', 'recipe_id', 'domain', 'repository', 'status', 'dokploy_app_id', 'pool_ids', 'meta', 'options', 'toolkit_state',
    ];

    protected function casts(): array
    {
        return [
            'pool_ids' => 'array',
            'meta' => 'array',
            'options' => 'array',
            'toolkit_state' => 'array',
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
}
