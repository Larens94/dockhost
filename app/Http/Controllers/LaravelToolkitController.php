<?php

// LaravelToolkitController.php — LaravelToolkitController module.
//
// exports: LaravelToolkitController | LaravelToolkitController::index(): Response | LaravelToolkitController::status(Domain $domain, LaravelToolkitExecutor $executor): JsonResponse | LaravelToolkitController::artisan( RunLaravelToolkitArtisanRequest $request, Domain $domain, LaravelToolkitExecutor $executor, ): JsonResponse | LaravelToolkitController::composer( RunLaravelToolkitComposerRequest $request, Domain $domain, LaravelToolkitExecutor $executor, ): JsonResponse | LaravelToolkitController::npm( RunLaravelToolkitNpmRequest $request, Domain $domain, LaravelToolkitExecutor $executor, ): JsonResponse
// used_by: routes/web.php
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_toolkit_catalog | Added npm endpoint for Toolkit.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Http\Controllers;

use App\Enums\DomainStack;
use App\Http\Requests\RunLaravelToolkitArtisanRequest;
use App\Http\Requests\RunLaravelToolkitComposerRequest;
use App\Http\Requests\RunLaravelToolkitNpmRequest;
use App\Models\Domain;
use App\Services\Laravel\LaravelToolkitExecutor;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class LaravelToolkitController extends Controller
{
    public function index(): Response
    {
        $domains = Domain::query()
            ->where('stack', DomainStack::Laravel)
            ->whereHas('dokployApplication')
            ->with(['customer', 'subscription', 'dokployApplication', 'infrastructure'])
            ->orderBy('fqdn')
            ->orderBy('id')
            ->get();

        $domains->each->makeHidden('infrastructure');

        return Inertia::render('LaravelToolkit/Index', [
            'domains' => $domains,
        ]);
    }

    public function status(Domain $domain, LaravelToolkitExecutor $executor): JsonResponse
    {
        $domain->load(['dokployApplication', 'infrastructure']);

        return response()->json($executor->overview($domain));
    }

    public function artisan(
        RunLaravelToolkitArtisanRequest $request,
        Domain $domain,
        LaravelToolkitExecutor $executor,
    ): JsonResponse {
        return response()->json($executor->artisan($domain, $request->validated('command')));
    }

    public function composer(
        RunLaravelToolkitComposerRequest $request,
        Domain $domain,
        LaravelToolkitExecutor $executor,
    ): JsonResponse {
        return response()->json($executor->composer($domain, $request->validated('command')));
    }

    public function npm(
        RunLaravelToolkitNpmRequest $request,
        Domain $domain,
        LaravelToolkitExecutor $executor,
    ): JsonResponse {
        return response()->json($executor->npm($domain, $request->validated('command')));
    }
}
