<?php


// ServicePlanController.php — ServicePlanController module.
//
// exports: ServicePlanController | ServicePlanController::index(): Response | ServicePlanController::create(): Response | ServicePlanController::store(StoreServicePlanRequest $request): RedirectResponse | ServicePlanController::show(ServicePlan $servicePlan): Response
// used_by: routes/web.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicePlanRequest;
use App\Models\ServicePlan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ServicePlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ServicePlans/Index', [
            'plans' => ServicePlan::query()
                ->withCount('subscriptions')
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ServicePlans/Create');
    }

    public function store(StoreServicePlanRequest $request): RedirectResponse
    {
        $plan = ServicePlan::query()->create($request->validated());

        return redirect()->route('service-plans.show', $plan);
    }

    public function show(ServicePlan $servicePlan): Response
    {
        $servicePlan->load([
            'subscriptions' => fn ($query) => $query->with('customer')->withCount('domains')->orderBy('name'),
        ]);

        return Inertia::render('ServicePlans/Show', [
            'plan' => $servicePlan,
        ]);
    }
}
