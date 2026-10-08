<?php


// CustomerController.php — CustomerController module.
//
// exports: CustomerController | CustomerController::index(): Response | CustomerController::create(): Response | CustomerController::store(StoreCustomerRequest $request): RedirectResponse | CustomerController::show(Customer $customer): Response | CustomerController::update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse | CustomerController::destroy(DestroyCustomerRequest $request, Customer $customer): RedirectResponse
// used_by: routes/web.php
// rules:   Customer is panel anagrafica only — no Dokploy calls here.
//          Domain/stack hosting flows go through DomainController + Hosting services.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | panel-boundary rule 

namespace App\Http\Controllers;

use App\Http\Requests\DestroyCustomerRequest;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\ServicePlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Customers/Index', [
            'customers' => Customer::query()
                ->withCount(['domains', 'subscriptions'])
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->validated());

        return redirect()->route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'subscriptions' => fn ($query) => $query
                ->with(['servicePlan'])
                ->withCount('domains')
                ->orderBy('name')
                ->orderBy('id'),
            'domains' => fn ($query) => $query->orderBy('fqdn')->orderBy('id'),
            'domains.databaseAccounts',
            'domains.dokployApplication',
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'plans' => ServicePlan::query()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.show', $customer);
    }

    public function destroy(DestroyCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $request->validated();
        $customer->delete();

        return redirect()->route('customers.index');
    }
}
