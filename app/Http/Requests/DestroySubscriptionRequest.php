<?php

// DestroySubscriptionRequest.php — Confirms space deletion before Dokploy teardown.
//
// exports: DestroySubscriptionRequest | DestroySubscriptionRequest::authorize(): bool | DestroySubscriptionRequest::rules(): array | DestroySubscriptionRequest::messages(): array
// used_by: app/Http/Controllers/SubscriptionController.php
// rules:   Confirmation must match Subscription::deletionConfirmation() — the only domain FQDN, or the space name otherwise.
// agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Typed confirmation before deleting a space.
// message:

namespace App\Http\Requests;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DestroySubscriptionRequest extends FormRequest
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

        if (! $subscription instanceof Subscription) {
            return [
                'confirmation' => ['required', 'string'],
            ];
        }

        $subscription->load('domains');

        return [
            'confirmation' => ['required', 'string', Rule::in([$subscription->deletionConfirmation()])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.required' => __('panel.validation.space_confirm_required'),
            'confirmation.in' => __('panel.validation.confirm_mismatch'),
        ];
    }
}
