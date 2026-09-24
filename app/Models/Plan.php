<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'stripe_price_id',
        'amount_cents',
        'currency',
        'interval',
        'site_quota',
        'features',
        'entitlements',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'entitlements' => 'array',
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
