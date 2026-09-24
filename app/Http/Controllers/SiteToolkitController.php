<?php

// SiteToolkitController.php — SiteToolkitController module.
//
// exports: SiteToolkitController | SiteToolkitController::show(Site $site): Response | SiteToolkitController::updateSettings(Request $request, Site $site): RedirectResponse | SiteToolkitController::runArtisan(Request $request, Site $site): RedirectResponse
// used_by: routes/web.php
// rules:   dokploy_boundary — deep-link Dokploy only; never rebuild deploy/SSL/logs/cron UI
// agent:   composer | cursor | 2026-09-18 | s_20260918_dokploy_stripe | Better Dokploy deep-links + app_id in toolkit props
// message:

namespace App\Http\Controllers;

use App\Contracts\InfrastructureDriver;
use App\Models\Pool;
use App\Models\Site;
use App\Models\SiteDomain;
use App\Services\EnvironmentBuilder;
use App\Services\RecipeExecutor;
use App\Services\SiteProvisioningService;
use App\Support\ApplicationToolkit;
use App\Support\ArtisanAllowlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteToolkitController extends Controller
{
    public function show(Site $site, EnvironmentBuilder $environment): Response
    {
        $site->load(['client', 'recipe', 'databaseAccount', 'sftpAccount', 'domains']);
        $stack = $site->recipe?->stack ?? 'generic';
        $toolkit = $site->recipe?->toolkit ?: ApplicationToolkit::forStack($stack);

        $state = array_merge([
            'schedule_enabled' => false,
            'queue_enabled' => false,
            'maintenance' => false,
            'queue_jobs' => 0,
            'last_commit' => null,
            'env_preview' => "APP_NAME=DockHostSite\nAPP_ENV=production\nAPP_DEBUG=false\n",
        ], $site->toolkit_state ?? []);

        $poolNames = Pool::pluck('name', 'id');

        // Rules: Prefer DOKPLOY_URL base deep-links; Dokploy SPA routes vary — surface dokploy_app_id in UI.
        $dokployBase = rtrim((string) config('dockhost.dokploy.url'), '/') ?: null;
        $dokployAppId = $site->dokploy_app_id;

        return Inertia::render('Sites/Toolkit', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'repository' => $site->repository,
                'status' => $site->status,
                'client' => $site->client?->name,
                'recipe' => $site->recipe?->name,
                'stack' => $stack,
                'dokploy_app_id' => $dokployAppId,
                'pools' => collect($site->pool_ids ?? [])
                    ->map(fn ($id) => $poolNames[$id] ?? "#{$id}")
                    ->values()
                    ->all(),
                'options' => $site->options ?? [],
                'last_error' => $site->last_error,
                'database' => $site->databaseAccount ? [
                    'engine' => $site->databaseAccount->engine,
                    'schema' => $site->databaseAccount->schema_name,
                    'username' => $site->databaseAccount->username,
                    'password' => $site->databaseAccount->password,
                    'host' => $site->databaseAccount->host,
                    'port' => $site->databaseAccount->port,
                    'status' => $site->databaseAccount->status,
                ] : null,
                'sftp' => $site->sftpAccount ? [
                    'username' => $site->sftpAccount->username,
                    'password' => $site->sftpAccount->password,
                    'path' => $site->sftpAccount->chroot_path,
                    'status' => $site->sftpAccount->status,
                ] : null,
                'domains' => $site->domains->map(fn (SiteDomain $domain) => [
                    'id' => $domain->id,
                    'host' => $domain->host,
                    'primary' => $domain->primary,
                ])->values()->all(),
                'git_branch' => $site->options['git_branch'] ?? 'main',
            ],
            'toolkit' => $toolkit,
            'state' => $state,
            'envPreview' => $site->environment
                ? $environment->masked($site->environment)
                : ($state['env_preview'] ?? null),
            'artisanCommands' => ArtisanAllowlist::commands(),
            'dokployUrl' => $dokployBase,
            'dokployConfigured' => (bool) $dokployBase,
            'dokployLinks' => $this->dokployLinks($site),
            'pools' => $site->status === 'failed' ? [
                'database' => $this->poolsOf('database'),
                'storage' => $this->poolsOf('storage'),
                'cache' => $this->poolsOf('cache'),
            ] : null,
        ]);
    }

    public function refresh(Site $site, RecipeExecutor $executor): RedirectResponse
    {
        $site = $executor->refreshDeployStatus($site);

        if ($site->status !== 'active' && $site->last_error) {
            return back()->with('error', $site->last_error);
        }

        return back()->with('success', "Deploy status is {$site->status}.");
    }

    public function retry(Request $request, Site $site, SiteProvisioningService $provisioning): RedirectResponse
    {
        $data = $request->validate([
            'database_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
            'storage_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
            'cache_pool_id' => ['nullable', 'integer', 'exists:pools,id'],
        ]);

        $site = $provisioning->retry($site, $data);

        if ($site->status === 'failed') {
            return redirect()
                ->route('sites.toolkit', $site)
                ->with('error', $site->last_error ?: 'Provisioning failed.');
        }

        return redirect()
            ->route('sites.toolkit', $site)
            ->with('success', "{$site->domain} is {$site->status}.");
    }

    public function storeDomain(Request $request, Site $site, InfrastructureDriver $driver): RedirectResponse
    {
        $site->loadMissing('recipe');

        $data = $request->validate([
            'host' => ['required', 'string', 'max:255', 'unique:site_domains,host', 'regex:/^(?=.{1,255}$)[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/i'],
        ]);

        $host = strtolower($data['host']);
        $created = $driver->attachDomain([
            'domain' => $host,
            'application_id' => $site->dokploy_app_id,
            'port' => $site->recipe?->stack === 'node' ? 3000 : 80,
        ]);
        $raw = is_array($created['raw'] ?? null) ? $created['raw'] : [];
        $domainId = $raw['domainId'] ?? $raw['id'] ?? null;

        SiteDomain::query()->create([
            'site_id' => $site->id,
            'host' => $host,
            'primary' => false,
            'dokploy_domain_id' => is_string($domainId) ? $domainId : null,
        ]);

        return back()->with('success', "Domain {$host} attached.");
    }

    public function updateSettings(Request $request, Site $site, EnvironmentBuilder $environment, InfrastructureDriver $driver): RedirectResponse
    {
        $this->normalizeBooleans($request, ['schedule_enabled', 'queue_enabled', 'maintenance']);

        $data = $request->validate([
            'schedule_enabled' => ['sometimes', 'boolean'],
            'queue_enabled' => ['sometimes', 'boolean'],
            'maintenance' => ['sometimes', 'boolean'],
            'repository' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $state = $site->toolkit_state ?? [];
        foreach (['schedule_enabled', 'queue_enabled', 'maintenance'] as $key) {
            if (array_key_exists($key, $data)) {
                $state[$key] = (bool) $data[$key];
            }
        }

        if (array_key_exists('repository', $data)) {
            $site->repository = $data['repository'];
        }

        $site->toolkit_state = $state;
        $site->save();

        if ($site->environment || $site->databaseAccount) {
            $rendered = $environment->render($environment->persist($site));
            $driver->updateEnvironment([
                'application_id' => $site->dokploy_app_id,
                'env' => $rendered,
            ]);
        }

        if ($site->repository && $site->dokploy_app_id) {
            $driver->saveGitProvider([
                'application_id' => $site->dokploy_app_id,
                'repository' => $site->repository,
                'branch' => $site->options['git_branch'] ?? 'main',
            ]);
        }

        return back()->with('success', 'Toolkit settings updated.');
    }

    public function runArtisan(Request $request, Site $site, InfrastructureDriver $driver): RedirectResponse
    {
        $data = $request->validate([
            'command' => ['required', 'string', 'max:200', Rule::in(ArtisanAllowlist::commands())],
        ]);

        $result = $driver->exec([
            'application_id' => $site->dokploy_app_id,
            'command' => $data['command'],
        ]);

        $state = $site->toolkit_state ?? [];
        $history = $state['artisan_history'] ?? [];
        array_unshift($history, [
            'command' => $data['command'],
            'at' => now()->toDateTimeString(),
            'output' => $result['output'],
        ]);
        $state['artisan_history'] = array_slice($history, 0, 10);
        $site->toolkit_state = $state;
        $site->save();

        return back()->with('success', "Artisan recorded: {$data['command']}");
    }

    /**
     * @return array<string, string>|null
     */
    private function dokployLinks(Site $site): ?array
    {
        $base = rtrim((string) config('dockhost.dokploy.url'), '/');

        if ($base === '') {
            return null;
        }

        $projectId = $site->meta['dokploy_project_id'] ?? null;
        $environmentId = $site->meta['dokploy_environment_id'] ?? config('dockhost.dokploy.environment_id');
        $applicationId = $site->dokploy_app_id;
        $app = is_string($projectId) && $projectId !== ''
            && is_string($environmentId) && $environmentId !== ''
            && is_string($applicationId) && $applicationId !== ''
            && ! str_starts_with($applicationId, 'local_')
            ? "{$base}/dashboard/project/{$projectId}/environment/{$environmentId}/services/application/{$applicationId}"
            : $base;

        return [
            'application' => $app,
            'deployment' => $app,
            'domains' => $app,
            'logs' => $app,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function poolsOf(string $kind): array
    {
        return Pool::query()
            ->where('kind', $kind)
            ->orderBy('name')
            ->get()
            ->map(fn (Pool $pool) => [
                'id' => $pool->id,
                'name' => $pool->name,
                'engine' => $pool->engine,
                'usage' => $pool->usage,
                'capacity' => $pool->capacity,
                'available' => $pool->usage < $pool->capacity,
            ])
            ->all();
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
}
