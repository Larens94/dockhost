<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(): Response
    {
        $plans = Plan::query()->where('active', true)->orderBy('amount_cents')->get()->map(fn (Plan $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->formattedPrice(),
            'interval' => $p->interval,
            'site_quota' => $p->site_quota,
            'features' => $p->features ?? [],
            'stripe_price_id' => $p->stripe_price_id,
        ]);

        $subscriptions = Subscription::query()
            ->with(['client', 'plan'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'client' => $s->client?->name,
                'plan' => $s->plan?->name,
                'status' => $s->status,
                'period_end' => optional($s->current_period_end)->toDateString(),
                'stripe_subscription_id' => $s->stripe_subscription_id,
            ]);

        return Inertia::render('Billing/Index', [
            'plans' => $plans,
            'subscriptions' => $subscriptions,
            'stripeConfigured' => (bool) config('dockhost.stripe.secret'),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email', 'billing_status']),
        ]);
    }

    public function assign(Request $request, StripeBillingService $billing): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $result = $billing->subscribe(
            Client::query()->findOrFail($data['client_id']),
            Plan::query()->findOrFail($data['plan_id']),
        );

        return redirect()
            ->route('billing.index')
            ->with('success', $result['subscription']->status === 'active'
                ? 'Subscription activated (stub or live).'
                : 'Subscription created — complete Stripe Checkout when wired.');
    }
}
