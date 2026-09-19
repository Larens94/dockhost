<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'amount_cents',
        'currency',
        'interval',
        'site_quota',
        'features',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function formattedPrice(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.').' '.strtoupper($this->currency);
    }
}
