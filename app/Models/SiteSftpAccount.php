<?php

namespace App\Models;

use Database\Factories\SiteSftpAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSftpAccount extends Model
{
    /** @use HasFactory<SiteSftpAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'site_id',
        'pool_id',
        'username',
        'password',
        'chroot_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
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
