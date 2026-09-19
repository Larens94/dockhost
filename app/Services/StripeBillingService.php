<?php
// StripeBillingService.php — StripeBillingService module.
//
// exports: StripeBillingService | StripeBillingService::configured(): bool | StripeBillingService::subscribe(Client $client, Plan $plan): array
// used_by: app/Http/Controllers/BillingController.php
//         app/Http/Controllers/WizardController.php
// rules:   configured() stubs safely — never break stub mode; Checkout Session not live yet
// agent:   composer | cursor | 2026-09-18 | s_20260918_dokploy_stripe | Document Checkout Session TODO; keep stub mode safe
// message: When STRIPE_SECRET set, still returns checkout_url=null until Checkout Session is wired

namespace App\Services;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stripe subscription orchestration for DockHost billing.
 * Deploy/SSL/logs stay in Dokploy — this only handles commercial lifecycle.
 *
 * Env: STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET (see .env.example).
 *
 * TODO — Stripe Checkout Session (when configured()):
 *   1. Create/reuse Stripe Customer (Customer::create) instead of cus_stub_*
 *   2. \Stripe\Checkout\Session::create([
 *        'mode' => 'subscription',
 *        'customer' => $client->stripe_customer_id,
 *        'line_items' => [['price' => $plan->stripe_price_id, 'quantity' => 1]],
 *        'success_url' / 'cancel_url' => billing routes,
 *      ])
 *   3. Return session->url as checkout_url; leave subscription status=incomplete
 *   4. Activate via webhook (checkout.session.completed) using STRIPE_WEBHOOK_SECRET
 *
 * Until then: stub mode (!configured) activates immediately; configured() still
 * creates an incomplete local subscription with checkout_url=null (safe no-op).
 */
class StripeBillingService
{
    public function configured(): bool
    {
        return (bool) config('dockhost.stripe.secret');
    }

    /**
     * Create or reuse Stripe customer + start subscription (stub-friendly).
     *
     * Rules: MUST keep stub path when !configured(); configured path must not throw
     *        or redirect until Checkout Session is implemented.
     *
     * @return array{subscription: Subscription, checkout_url: string|null}
     */
    public function subscribe(Client $client, Plan $plan): array
    {
        if (! $client->stripe_customer_id) {
            // Stub id even when configured — real Customer::create lands with Checkout Session TODO above.
            $client->stripe_customer_id = 'cus_stub_'.Str::lower(Str::random(10));
            $client->billing_email = $client->billing_email ?: $client->email;
            $client->billing_status = 'pending';
            $client->save();
        }

        $subscription = Subscription::query()->updateOrCreate(
            [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
            ],
            [
                'stripe_subscription_id' => $this->configured()
                    ? null
                    : 'sub_stub_'.Str::lower(Str::random(10)),
                'status' => $this->configured() ? 'incomplete' : 'active',
                'current_period_end' => now()->addMonth(),
                'meta' => [
                    'mode' => $this->configured() ? 'stripe' : 'stub',
                ],
            ]
        );

        if (! $this->configured()) {
            $client->billing_status = 'active';
            $client->save();
            Log::info('Stripe stub subscription activated', [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
            ]);
        } else {
            // Configured but Checkout Session not wired — safe stub: no redirect, no API call.
            Log::info('Stripe configured but Checkout Session TODO — incomplete local subscription', [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
            ]);
        }

        return [
            'subscription' => $subscription,
            // TODO: return Checkout Session URL when Session::create is wired (see class docblock).
            'checkout_url' => null,
        ];
    }
}
