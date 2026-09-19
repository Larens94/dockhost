<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfraTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'summary', 'version', 'services', 'compose',
    ];

    protected function casts(): array
    {
        return ['services' => 'array'];
    }
}
