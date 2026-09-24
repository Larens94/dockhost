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
use App\Services\EnvironmentBuilder;
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
        $site->load(['client', 'recipe', 'databaseAccount', 'sftpAccount']);
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
            ],
            'toolkit' => $toolkit,
            'state' => $state,
            'envPreview' => $site->environment
                ? $environment->masked($site->environment)
                : ($state['env_preview'] ?? null),
            'artisanCommands' => ArtisanAllowlist::commands(),
            'dokployUrl' => $dokployBase,
            'dokployConfigured' => (bool) $dokployBase,
            // Useful deep-links when configured — all open Dokploy base (no DockHost rebuild of Dokploy UI).
            'dokployLinks' => $dokployBase
                ? [
                    'home' => $dokployBase,
                    'domain' => $dokployBase,
                    'logs' => $dokployBase,
                    'terminal' => $dokployBase,
                    'deployment' => $dokployBase,
                ]
                : null,
        ]);
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
