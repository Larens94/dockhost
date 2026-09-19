<?php
// Client.php — Client module.
//
// exports: Client | Client::sites(): HasMany | Client::subscriptions(): HasMany
// used_by: app/Http/Controllers/BillingController.php
//                   app/Http/Controllers/ClientController.php
//                   app/Http/Controllers/PanelController.php
//                   app/Http/Controllers/WizardController.php
//                   app/Services/StripeBillingService.php
//                   database/seeders/DatabaseSeeder.php
//                   tests/Feature/ClientCrudTest.php
// rules:   Anagrafica CRUD mutates name/company/email/billing_email/status only; billing_status is billing-owned
// agent:   composer | cursor | 2026-09-18 | s_20260918_client_crud | Annotate ClientController consumer + anagrafica rules
// message: 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name',
        'company',
        'email',
        'status',
        'stripe_customer_id',
        'billing_email',
        'billing_status',
        'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
