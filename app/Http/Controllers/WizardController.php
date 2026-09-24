<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Pool;
use App\Models\Recipe;
use App\Services\EntitlementGate;
use App\Services\SiteProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WizardController extends Controller
{
    public function create(EntitlementGate $entitlements): Response
    {
        $clients = Client::query()
            ->with(['subscriptions.plan'])
            ->orderBy('name')
            ->get()
            ->map(function (Client $client) use ($entitlements) {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'billing_status' => $client->billing_status,
                    'status' => $client->status,
                    ...$entitlements->summary($client),
                ];
            });

        return Inertia::render('Wizard/Provision', [
            'clients' => $clients,
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
        $this->normalizeBooleans($request, [
            'wants_database',
            'wants_storage',
            'wants_sftp',
            'wants_cache',
        ]);

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'domain' => ['required', 'string', 'max:255', 'unique:sites,domain', 'regex:/^(?=.{1,255}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i'],
            'repository' => ['nullable', 'string', 'max:255'],
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

        if ($site->status === 'failed') {
            return redirect()
                ->route('sites.toolkit', $site)
                ->with('error', $site->last_error ?: 'Provisioning failed.');
        }

        return redirect()
            ->route('sites.toolkit', $site)
            ->with('success', "{$site->domain} is {$site->status}. Deploy and SSL continue in Dokploy.");
    }

    /**
     * @param  list<string>  $keys
     */
    private function normalizeBooleans(Request $request, array $keys): void
    {
        $merged = [];

        foreach ($keys as $key) {
            if ($request->exists($key)) {
                $merged[$key] = filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        $request->merge($merged);
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
