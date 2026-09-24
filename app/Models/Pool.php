<?php

namespace App\Models;

use Database\Factories\PoolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pool extends Model
{
    /** @use HasFactory<PoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'kind', 'engine', 'runtime_version', 'server_id', 'capacity', 'usage', 'dokploy_ref', 'meta',
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
