<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCatalogItem extends Model
{
    protected $table = 'service_catalog';

    protected $fillable = [
        'name', 'kind', 'image', 'mode', 'support', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
