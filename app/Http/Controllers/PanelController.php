<?php

// PanelController.php — Inertia panel read endpoints.
//
// exports: PanelController::dashboard|clients|sites|pools|servers|services|recipes|templates|dokploySettings
// used_by: routes/web.php
// rules:   dokploySettings must pass dokploy_owns; never rebuild Dokploy UI here
//          clients/sites lists: server-side q/status/sort/page via LengthAwarePaginator
// agent:   composer | cursor | 2026-09-18 | s_20260918_list_tables | Clients/Sites data tables + filters
// message: "Client mutations live on ClientController; list UX is paginated tables."

namespace App\Http\Controllers;

use App\Contracts\InfrastructureDriver;
use App\Models\Audit;
use App\Models\Client;
use App\Models\InfraTemplate;
use App\Models\Pool;
use App\Models\Recipe;
use App\Models\Server;
use App\Models\ServiceCatalogItem;
use App\Models\Site;
use App\Services\PoolLedger;
use App\Support\OperatorError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class PanelController extends Controller
{
    public function dashboard(): Response
    {
        $pools = Pool::with('server')->orderBy('name')->get()->map(fn (Pool $pool) => [
            'id' => $pool->id,
            'name' => $pool->name,
            'kind' => $pool->kind,
            'server' => $pool->server?->name,
            'usage' => $pool->usage,
            'capacity' => $pool->capacity,
        ]);

        $recentSites = Site::with(['client', 'recipe'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Site $site) => [
                'id' => $site->id,
                'domain' => $site->domain,
                'client' => $site->client?->name,
                'recipe' => $site->recipe?->name,
                'status' => $site->status,
            ]);

        $audits = Audit::query()
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Audit $audit) => [
                'id' => $audit->id,
                'action' => $audit->action,
                'actor' => $audit->user?->name,
                'meta' => $audit->meta ?? [],
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'clients' => Client::count(),
                'sites' => Site::count(),
                'pools' => Pool::count(),
                'recipes' => Recipe::count(),
            ],
            'recentSites' => $recentSites,
            'pools' => $pools,
            'audits' => $audits,
        ]);
    }

    public function clients(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'billing_status' => (string) $request->query('billing_status', ''),
            'sort' => (string) $request->query('sort', 'name'),
            'direction' => strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc',
        ];

        $sort = in_array($filters['sort'], ['name', 'created_at'], true)
            ? $filters['sort']
            : 'name';
        $filters['sort'] = $sort;

        $query = Client::query()->withCount('sites');

        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($needle) {
                $q->where('name', 'like', $needle)
                    ->orWhere('company', 'like', $needle)
                    ->orWhere('email', 'like', $needle);
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['billing_status'] !== '') {
            $query->where('billing_status', $filters['billing_status']);
        }

        $clients = $query
            ->orderBy($sort, $filters['direction'])
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Client $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'company' => $c->company,
                'email' => $c->email,
                'status' => $c->status,
                'billing_status' => $c->billing_status,
                'sites_count' => $c->sites_count,
                'created_at' => $c->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $filters,
            'filterOptions' => [
                'statuses' => Client::query()->distinct()->orderBy('status')->pluck('status')->values(),
                'billingStatuses' => Client::query()->distinct()->orderBy('billing_status')->pluck('billing_status')->values(),
            ],
        ]);
    }

    public function sites(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'recipe' => (string) $request->query('recipe', ''),
            'stack' => (string) $request->query('stack', ''),
            'sort' => (string) $request->query('sort', 'created_at'),
            'direction' => strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];

        $sort = in_array($filters['sort'], ['domain', 'created_at'], true)
            ? $filters['sort']
            : 'created_at';
        $filters['sort'] = $sort;

        $poolNames = Pool::pluck('name', 'id');

        $query = Site::query()->with(['client', 'recipe']);

        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $query->where(function ($q) use ($needle) {
                $q->where('domain', 'like', $needle)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $needle))
                    ->orWhereHas('recipe', fn ($r) => $r->where('name', 'like', $needle));
            });
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['recipe'] !== '') {
            $query->whereHas('recipe', function ($r) use ($filters) {
                $r->where(function ($inner) use ($filters) {
                    $inner->where('slug', $filters['recipe'])
                        ->orWhere('name', $filters['recipe']);
                });
            });
        }

        if ($filters['stack'] !== '') {
            $query->whereHas('recipe', fn ($r) => $r->where('stack', $filters['stack']));
        }

        $sites = $query
            ->orderBy($sort, $filters['direction'])
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Site $site) use ($poolNames) {
                return [
                    'id' => $site->id,
                    'domain' => $site->domain,
                    'client' => $site->client?->name,
                    'recipe' => $site->recipe?->name,
                    'recipe_slug' => $site->recipe?->slug,
                    'stack' => $site->recipe?->stack,
                    'status' => $site->status,
                    'last_error' => OperatorError::present($site->last_error),
                    'pools' => collect($site->pool_ids ?? [])
                        ->map(fn ($id) => $poolNames[$id] ?? "#{$id}")
                        ->values()
                        ->all(),
                    'created_at' => $site->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'filters' => $filters,
            'filterOptions' => [
                'statuses' => Site::query()->distinct()->orderBy('status')->pluck('status')->values(),
                'recipes' => Recipe::query()->orderBy('name')->get(['name', 'slug', 'stack'])->map(fn (Recipe $r) => [
                    'name' => $r->name,
                    'slug' => $r->slug,
                    'stack' => $r->stack,
                ]),
                'stacks' => Recipe::query()->distinct()->orderBy('stack')->pluck('stack')->values(),
            ],
        ]);
    }

    public function pools(): Response
    {
        $pools = Pool::with('server')->orderBy('kind')->get()->map(fn (Pool $pool) => [
            'id' => $pool->id,
            'name' => $pool->name,
            'kind' => $pool->kind,
            'engine' => $pool->engine,
            'runtime_version' => $pool->runtime_version,
            'server' => $pool->server?->name,
            'usage' => $pool->usage,
            'capacity' => $pool->capacity,
            'dokploy_ref' => $pool->dokploy_ref,
            'admin_ready' => filled($pool->adminConnection()['host']) && filled($pool->adminConnection()['admin_username']),
            'ssh_ready' => filled($pool->adminConnection()['ssh_host']) && filled($pool->adminConnection()['ssh_username']),
        ]);

        return Inertia::render('Pools/Index', compact('pools'));
    }

    public function servers(): Response
    {
        $servers = Server::orderBy('name')->get()->map(fn (Server $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'ip' => $s->ip,
            'role' => $s->role,
            'status' => $s->status,
            'dokploy_server_id' => $s->dokploy_server_id,
        ]);

        return Inertia::render('Servers/Index', compact('servers'));
    }

    public function services(): Response
    {
        $services = ServiceCatalogItem::orderBy('name')->get()->map(fn (ServiceCatalogItem $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'kind' => $s->kind,
            'image' => $s->image,
            'mode' => $s->mode,
            'support' => $s->support,
            'dokploy_ref' => $s->meta['dokploy_ref'] ?? null,
        ]);

        return Inertia::render('Services/Index', compact('services'));
    }

    public function recipes(): Response
    {
        $recipes = Recipe::orderBy('name')->get()->map(fn (Recipe $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'stack' => $r->stack,
            'summary' => $r->summary,
            'version' => $r->version,
            'status' => $r->status,
            'requires' => $r->requires ?? [],
        ]);

        return Inertia::render('Recipes/Index', compact('recipes'));
    }

    public function templates(): Response
    {
        $templates = InfraTemplate::orderBy('name')->get()->map(fn (InfraTemplate $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'summary' => $t->summary,
            'version' => $t->version,
            'services' => $t->services ?? [],
            'dokploy_ref' => $t->dokploy_ref,
        ]);

        return Inertia::render('Templates/Index', compact('templates'));
    }

    public function dokploySettings(): Response
    {
        $key = Config::get('dockhost.dokploy.api_key');

        return Inertia::render('Settings/Dokploy', [
            'settings' => [
                'url' => Config::get('dockhost.dokploy.url') ?: '',
                'api_key_masked' => $key ? str_repeat('•', 8).substr((string) $key, -4) : '',
                'driver' => Config::get('dockhost.driver', 'dokploy'),
                'connected' => (bool) Config::get('dockhost.dokploy.url') && (bool) $key,
            ],
            'dokployOwns' => Config::get('dockhost.dokploy_owns', []),
        ]);
    }

    public function pingDokploy(InfrastructureDriver $driver): RedirectResponse
    {
        $result = $driver->ping();

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['message'] ?? 'Dokploy did not respond.',
        );
    }

    public function syncServers(InfrastructureDriver $driver, PoolLedger $ledger): RedirectResponse
    {
        $result = $driver->listServers();

        if (! $result['ok']) {
            return back()->with('error', $result['message'] ?? 'Could not list Dokploy servers.');
        }

        foreach ($result['servers'] as $server) {
            if ($server['id'] === '') {
                continue;
            }

            Server::query()->updateOrCreate(
                ['dokploy_server_id' => $server['id']],
                [
                    'name' => $server['name'],
                    'ip' => $server['ip'],
                    'status' => 'online',
                    'role' => 'worker',
                ]
            );
        }

        $ledger->recalculate(Pool::query()->pluck('id')->all());

        return back()->with('success', count($result['servers']).' servers synced from Dokploy.');
    }
}
