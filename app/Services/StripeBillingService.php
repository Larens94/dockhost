<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Commercial lifecycle only. Deploy, SSL, and logs stay in Dokploy.
 *
 * Without STRIPE_SECRET the subscription activates locally.
 * With a real price id, Checkout Session is created and stays incomplete
 * until the webhook marks it active.
 */
class StripeBillingService
{
    public function __construct(private SiteSuspension $sites) {}

    public function configured(): bool
    {
        return (bool) config('dockhost.stripe.secret');
    }

    /**
     * @return array{subscription: Subscription, checkout_url: string|null}
     */
    public function subscribe(Client $client, Plan $plan): array
    {
        $live = $this->livePrice($plan);

        if (! $client->stripe_customer_id || str_starts_with((string) $client->stripe_customer_id, 'cus_stub_')) {
            $client->stripe_customer_id = $live
                ? $this->createCustomer($client)
                : 'cus_stub_'.Str::lower(Str::random(10));
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
                'stripe_subscription_id' => $live ? null : 'sub_stub_'.Str::lower(Str::random(10)),
                'status' => $live ? 'incomplete' : 'active',
                'current_period_end' => now()->addMonth(),
                'meta' => [
                    'mode' => $live ? 'stripe' : 'stub',
                ],
            ]
        );

        if (! $live) {
            $client->billing_status = 'active';
            $client->save();
            Log::info('Stripe stub subscription activated', [
                'client_id' => $client->id,
                'plan_id' => $plan->id,
            ]);

            return [
                'subscription' => $subscription,
                'checkout_url' => null,
            ];
        }

        $checkoutUrl = $this->createCheckoutSession($client, $plan, $subscription);

        return [
            'subscription' => $subscription,
            'checkout_url' => $checkoutUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function applyWebhook(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        if (! is_array($object)) {
            return;
        }

        if ($type === 'checkout.session.completed') {
            $this->activateFromCheckout($object);
        }

        if ($type === 'invoice.payment_failed') {
            $this->markPastDue((string) ($object['customer'] ?? ''));
        }

        if (in_array($type, ['invoice.paid', 'invoice.payment_succeeded'], true)) {
            $this->markRecovered((string) ($object['customer'] ?? ''));
        }

        if ($type === 'customer.subscription.deleted') {
            $this->cancelSubscription((string) ($object['id'] ?? ''));
        }
    }

    public function livePrice(Plan $plan): bool
    {
        return $this->configured()
            && is_string($plan->stripe_price_id)
            && $plan->stripe_price_id !== ''
            && ! str_contains($plan->stripe_price_id, 'stub');
    }

    private function createCustomer(Client $client): string
    {
        $response = $this->stripe()->post('/customers', [
            'email' => $client->billing_email ?: $client->email,
            'name' => $client->company ?: $client->name,
            'metadata' => [
                'client_id' => (string) $client->id,
            ],
        ]);

        if (! $response->successful() || ! $response->json('id')) {
            throw ValidationException::withMessages([
                'plan_id' => 'Stripe could not create the customer.',
            ]);
        }

        return (string) $response->json('id');
    }

    private function createCheckoutSession(Client $client, Plan $plan, Subscription $subscription): string
    {
        $response = $this->stripe()->post('/checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $client->stripe_customer_id,
            'success_url' => route('billing.index').'?checkout=success',
            'cancel_url' => route('billing.index').'?checkout=cancel',
            'line_items' => [
                [
                    'price' => $plan->stripe_price_id,
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'client_id' => (string) $client->id,
                'plan_id' => (string) $plan->id,
                'subscription_id' => (string) $subscription->id,
            ],
        ]);

        $url = $response->json('url');

        if (! $response->successful() || ! is_string($url) || $url === '') {
            throw ValidationException::withMessages([
                'plan_id' => 'Stripe could not start Checkout.',
            ]);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function activateFromCheckout(array $session): void
    {
        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $subscriptionId = $metadata['subscription_id'] ?? null;

        if (! $subscriptionId) {
            return;
        }

        $subscription = Subscription::query()->find($subscriptionId);

        if (! $subscription) {
            return;
        }

        $subscription->status = 'active';
        $subscription->stripe_subscription_id = is_string($session['subscription'] ?? null)
            ? $session['subscription']
            : $subscription->stripe_subscription_id;
        $subscription->current_period_end = now()->addMonth();
        $subscription->save();

        $subscription->client?->forceFill([
            'billing_status' => 'active',
            'stripe_customer_id' => is_string($session['customer'] ?? null)
                ? $session['customer']
                : $subscription->client->stripe_customer_id,
        ])->save();

        if ($subscription->client) {
            $this->sites->apply($subscription->client);
        }
    }

    private function markPastDue(string $customerId): void
    {
        if ($customerId === '') {
            return;
        }

        $client = Client::query()->where('stripe_customer_id', $customerId)->first();

        if (! $client) {
            return;
        }

        $client->billing_status = 'past_due';
        $client->save();
        $client->subscriptions()->whereIn('status', ['active', 'trialing'])->update([
            'status' => 'past_due',
        ]);
        $this->sites->apply($client);
    }

    private function cancelSubscription(string $stripeSubscriptionId): void
    {
        if ($stripeSubscriptionId === '') {
            return;
        }

        $subscription = Subscription::query()
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if (! $subscription) {
            return;
        }

        $subscription->status = 'canceled';
        $subscription->save();

        $stillActive = $subscription->client?->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->exists();

        if (! $stillActive && $subscription->client) {
            $subscription->client->billing_status = 'canceled';
            $subscription->client->save();
        }

        if ($subscription->client) {
            $this->sites->apply($subscription->client);
        }
    }

    private function markRecovered(string $customerId): void
    {
        if ($customerId === '') {
            return;
        }

        $client = Client::query()->where('stripe_customer_id', $customerId)->first();

        if (! $client || $client->billing_status === 'active') {
            return;
        }

        $client->billing_status = 'active';
        $client->save();
        $client->subscriptions()->where('status', 'past_due')->update([
            'status' => 'active',
        ]);
        $this->sites->apply($client);
    }

    private function stripe(): PendingRequest
    {
        return Http::withToken((string) config('dockhost.stripe.secret'))
            ->asForm()
            ->acceptJson()
            ->baseUrl('https://api.stripe.com/v1');
    }
}
