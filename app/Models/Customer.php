<?php


// Customer.php — Customer module.
//
// exports: Customer | attr:Fillable | Customer::subscriptions(): HasMany | Customer::domains(): HasMany | Customer::storageSlug(): string
// used_by: app/Http/Controllers/CustomerController.php
//         app/Http/Controllers/SubscriptionController.php
//         app/Http/Requests/DestroyCustomerRequest.php
//         app/Http/Requests/StoreSubscriptionRequest.php
//         database/factories/CustomerFactory.php
//         database/factories/DomainFactory.php
//         database/factories/SubscriptionFactory.php
//         tests/Feature/CustomerTest.php
//         tests/Feature/DomainProvisionTest.php
//         tests/Feature/HostingFlowTest.php
//         tests/Feature/SubscriptionTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Models;

use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'notes'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function storageSlug(): string
    {
        $slug = Str::slug($this->name);

        return $slug !== '' ? $slug : 'customer-'.$this->id;
    }
}
