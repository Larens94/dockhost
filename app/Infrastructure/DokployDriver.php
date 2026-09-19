<?php
// DokployDriver.php — DokployDriver module.
//
// exports: DokployDriver | DokployDriver::name(): string | DokployDriver::ping(): array | DokployDriver::deployCompose(array $definition): array | DokployDriver::deployApplication(array $definition): array | DokployDriver::attachDomain(array $definition): array
// used_by: app/Providers/AppServiceProvider.php
// rules:   stubs only until live API wired; never invent Dokploy UI in DockHost
// agent:   composer | cursor | 2026-09-18 | s_20260918_dokploy_stripe | Document real API endpoint TODOs (compose/application/domain)
// message: Live calls still stubbed — wire compose.create / application.create / domain.create next

namespace App\Infrastructure;

use App\Contracts\InfrastructureDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dokploy API adapter. Business entities never call this directly
 * from controllers without going through application services.
 *
 * Real Dokploy HTTP endpoints (to wire when credentials + payload shapes are confirmed):
 *   POST {DOKPLOY_URL}/api/compose.create      — create compose service
 *   POST {DOKPLOY_URL}/api/compose.deploy      — deploy compose
 *   POST {DOKPLOY_URL}/api/application.create  — create application
 *   POST {DOKPLOY_URL}/api/application.deploy  — deploy application
 *   POST {DOKPLOY_URL}/api/domain.create       — attach domain / SSL
 *   GET  {DOKPLOY_URL}/api/project.all         — ping / list (already used)
 *
 * Auth header: x-api-key: {DOKPLOY_API_KEY}
 */
class DokployDriver implements InfrastructureDriver
{
    public function name(): string
    {
        return 'dokploy';
    }

    public function ping(): array
    {
        $url = config('dockhost.dokploy.url');
        $key = config('dockhost.dokploy.api_key');

        if (! $url || ! $key) {
            return ['ok' => false, 'message' => 'Dokploy URL or API key missing'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
            ])->timeout(8)->get(rtrim($url, '/').'/api/project.all');

            return [
                'ok' => $response->successful(),
                'message' => $response->successful() ? 'connected' : 'HTTP '.$response->status(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Dokploy ping failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function deployCompose(array $definition): array
    {
        // TODO: live call — POST {DOKPLOY_URL}/api/compose.create then compose.deploy
        //   headers: x-api-key
        //   body: name, appName, composeFile, sourceType, ...
        //   return external_id from response.composeId / applicationId
        // Stub until payload contract is confirmed — do not half-wire.
        return [
            'external_id' => $definition['external_id'] ?? null,
            'raw' => ['status' => 'stubbed', 'definition' => $definition],
        ];
    }

    public function deployApplication(array $definition): array
    {
        // TODO: live call — POST {DOKPLOY_URL}/api/application.create then application.deploy
        //   headers: x-api-key
        //   body: name, appName, sourceType (github/git/docker), buildType, ...
        //   return external_id from response.applicationId
        return [
            'external_id' => $definition['external_id'] ?? null,
            'raw' => ['status' => 'stubbed', 'definition' => $definition],
        ];
    }

    public function attachDomain(array $definition): array
    {
        // TODO: live call — POST {DOKPLOY_URL}/api/domain.create
        //   headers: x-api-key
        //   body: host, https, certificateType, applicationId|composeId, ...
        return [
            'ok' => true,
            'raw' => ['status' => 'stubbed', 'definition' => $definition],
        ];
    }
}
