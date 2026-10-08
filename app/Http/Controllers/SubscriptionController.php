<?php


// SubscriptionController.php — SubscriptionController module.
//
// exports: SubscriptionController | SubscriptionController::index(): Response | SubscriptionController::create(Request $request): Response | SubscriptionController::store(StoreSubscriptionRequest $request): RedirectResponse | SubscriptionController::show(Subscription $subscription): Response
// used_by: routes/web.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Customer;
use App\Models\ServicePlan;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => Subscription::query()
                ->with(['customer', 'servicePlan'])
                ->withCount('domains')
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Subscriptions/Create', [
            'customers' => Customer::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'plans' => ServicePlan::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug']),
            'selected_customer_id' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(StoreSubscriptionRequest $request): RedirectResponse
    {
        $subscription = Subscription::query()->create($request->validated());

        return redirect()->route('subscriptions.show', $subscription);
    }

    public function show(Subscription $subscription): Response
    {
        $subscription->load([
            'customer',
            'servicePlan',
            'domains' => fn ($query) => $query->orderBy('fqdn')->orderBy('id'),
            'domains.databaseAccounts',
            'domains.sftpUsers',
            'domains.dokployApplication',
            'domains.infrastructure',
        ]);

        $subscription->domains->each(function ($domain): void {
            $domain->makeHidden('infrastructure');
        });

        return Inertia::render('Subscriptions/Show', [
            'subscription' => $subscription,
        ]);
    }
}
