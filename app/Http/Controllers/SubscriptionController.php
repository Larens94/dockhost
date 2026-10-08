<?php

// SubscriptionController.php — SubscriptionController module.
//
// exports: SubscriptionController | SubscriptionController::index(): Response | SubscriptionController::create(Request $request): Response | SubscriptionController::store(StoreSubscriptionRequest $request): RedirectResponse | SubscriptionController::show(Subscription $subscription): Response | SubscriptionController::destroy(DestroySubscriptionRequest $request, Subscription $subscription, DomainProvisioner $provisioner): RedirectResponse
// used_by: routes/web.php
// rules:   Destroy decommissions every domain (Dokploy application.delete) before deleting the subscription. Confirmation is the sole domain FQDN, or the space name when there isn't exactly one.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// agent:   grok-4.7 | cursor | 2026-10-08 | s_delete_space | Delete space plus linked Dokploy apps.
// message:

namespace App\Http\Controllers;

use App\Http\Requests\DestroySubscriptionRequest;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Customer;
use App\Models\ServicePlan;
use App\Models\Subscription;
use App\Services\Hosting\DomainProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(): Response
    {
        $subscriptions = Subscription::query()
            ->with([
                'customer',
                'servicePlan',
                'domains.dokployApplication',
            ])
            ->withCount('domains')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $subscriptions->each(function (Subscription $subscription): void {
            $this->attachDeletionPreview($subscription);
            $subscription->unsetRelation('domains');
        });

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions,
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

        $this->attachDeletionPreview($subscription);

        return Inertia::render('Subscriptions/Show', [
            'subscription' => $subscription,
        ]);
    }

    public function destroy(
        DestroySubscriptionRequest $request,
        Subscription $subscription,
        DomainProvisioner $provisioner,
    ): RedirectResponse {
        $request->validated();

        $subscription->load(['domains.dokployApplication', 'domains.infrastructure']);

        try {
            foreach ($subscription->domains as $domain) {
                $provisioner->decommission($domain);
            }
        } catch (ValidationException $exception) {
            $message = $exception->validator->errors()->first();

            throw ValidationException::withMessages([
                'confirmation' => is_string($message) && $message !== ''
                    ? $message
                    : 'Eliminazione interrotta. Riprova.',
            ]);
        }

        $subscription->delete();

        return redirect()->route('subscriptions.index');
    }

    private function attachDeletionPreview(Subscription $subscription): void
    {
        $subscription->setAttribute('deletion_targets', $subscription->deletionTargets());
        $subscription->setAttribute('deletion_confirmation', $subscription->deletionConfirmation());
    }
}
