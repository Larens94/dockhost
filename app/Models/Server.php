<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    protected $fillable = [
        'name', 'ip', 'role', 'status', 'dokploy_server_id',
    ];

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }
}
