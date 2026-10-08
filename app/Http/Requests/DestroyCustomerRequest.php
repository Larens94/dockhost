<?php


// DestroyCustomerRequest.php — DestroyCustomerRequest module.
//
// exports: DestroyCustomerRequest | DestroyCustomerRequest::authorize(): bool | DestroyCustomerRequest::rules(): array | DestroyCustomerRequest::withValidator(Validator $validator): void
// used_by: app/Http/Controllers/CustomerController.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DestroyCustomerRequest extends FormRequest
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
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customer = $this->route('customer');

            if (! $customer instanceof Customer) {
                return;
            }

            if ($customer->subscriptions()->exists() || $customer->domains()->exists()) {
                $validator->errors()->add(
                    'customer',
                    'Elimina prima gli spazi e i domini di questo cliente.',
                );
            }
        });
    }
}
