<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDomain extends Model
{
    protected $fillable = [
        'site_id',
        'host',
        'primary',
        'dokploy_domain_id',
    ];

    protected function casts(): array
    {
        return [
            'primary' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
