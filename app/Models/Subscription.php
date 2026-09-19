<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'client_id',
        'plan_id',
        'stripe_subscription_id',
        'status',
        'current_period_end',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
