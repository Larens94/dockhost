<?php

namespace App\Models;

use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'ip', 'role', 'status', 'dokploy_server_id',
    ];

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }
}
