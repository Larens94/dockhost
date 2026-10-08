<?php


// StoreSubscriptionRequest.php — StoreSubscriptionRequest module.
//
// exports: StoreSubscriptionRequest | StoreSubscriptionRequest::authorize(): bool | StoreSubscriptionRequest::rules(): array
// used_by: app/Http/Controllers/SubscriptionController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'service_plan_id' => ['required', 'exists:service_plans,id'],
            'name' => ['required', 'string', 'max:255', 'unique:subscriptions,name'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->route('customer');

        if ($customer instanceof Customer) {
            $this->merge([
                'customer_id' => $customer->id,
            ]);
        } elseif (is_numeric($customer)) {
            $this->merge([
                'customer_id' => (int) $customer,
            ]);
        }
    }
}
