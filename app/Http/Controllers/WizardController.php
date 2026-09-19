<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Site;
use App\Models\Subscription;
use App\Services\SiteProvisioningService;
use App\Services\StripeBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Wizard/Provision', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'email', 'billing_status']),
            'recipes' => Recipe::query()
                ->where('enabled', true)
                ->orderBy('sort')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'stack', 'summary', 'status', 'requires']),
            'pools' => [
                'database' => $this->poolsOf('database'),
                'storage' => $this->poolsOf('storage'),
                'cache' => $this->poolsOf('cache'),
            ],
            'dokployOwns' => config('dockhost.dokploy_owns'),
        ]);
    }

    public function store(Request $request, SiteProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'domain' => ['required', 'string', 'max:255', 'unique:sites,domain'],
            'recipe_id' => ['required', 'exists:recipes,id'],
            'wants_database' => ['required', 'boolean'],
            'database_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
            'wants_storage' => ['required', 'boolean'],
            'storage_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
            'wants_sftp' => ['required', 'boolean'],
            'wants_cache' => ['required', 'boolean'],
            'cache_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
        ]);

        $site = $provisioning->provision($data);

        return redirect()
            ->route('sites.index')
            ->with('success', "Site {$site->domain} queued. Deploy/SSL continue in Dokploy.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function poolsOf(string $kind): array
    {
        return Pool::query()
            ->with('server')
            ->where('kind', $kind)
            ->orderBy('name')
            ->get()
            ->map(fn (Pool $pool) => [
                'id' => $pool->id,
                'name' => $pool->name,
                'engine' => $pool->engine,
                'server' => $pool->server?->name,
                'usage' => $pool->usage,
                'capacity' => $pool->capacity,
                'available' => $pool->usage < $pool->capacity,
            ])
            ->all();
    }
}
