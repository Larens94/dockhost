<?php

// UpdateSubscriptionRequest.php — Validates space (subscription) rename.
//
// exports: UpdateSubscriptionRequest | UpdateSubscriptionRequest::authorize(): bool | UpdateSubscriptionRequest::rules(): array
// used_by: app/Http/Controllers/SubscriptionController.php
// rules:   Admin-only via routes middleware. Name must stay unique among subscriptions.
// agent:   composer-2.5 | cursor | 2026-10-16 | s_rename_space | PUT subscriptions/{subscription} name only.

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
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
        $subscription = $this->route('subscription');
        $subscriptionId = $subscription instanceof Subscription ? $subscription->getKey() : $subscription;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('subscriptions', 'name')->ignore($subscriptionId),
            ],
        ];
    }
}
