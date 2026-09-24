<?php

namespace App\Models;

use Database\Factories\SiteDatabaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteDatabase extends Model
{
    /** @use HasFactory<SiteDatabaseFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'pool_id',
        'engine',
        'schema_name',
        'username',
        'password',
        'host',
        'port',
        'status',
        'dokploy_ref',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }
}
