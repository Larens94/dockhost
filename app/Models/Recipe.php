<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'name', 'slug', 'stack', 'summary', 'version', 'status', 'enabled', 'sort', 'requires', 'steps', 'toolkit',
    ];

    protected function casts(): array
    {
        return [
            'requires' => 'array',
            'steps' => 'array',
            'toolkit' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
