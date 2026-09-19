<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pool extends Model
{
    protected $fillable = [
        'name', 'kind', 'engine', 'server_id', 'capacity', 'usage', 'dokploy_ref', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
