<?php

namespace App\Http\Controllers;

use App\Models\Pool;
use App\Models\Server;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PoolController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Pools/Form', [
            'pool' => null,
            'servers' => $this->servers(),
            'kinds' => $this->kinds(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $pool = Pool::query()->create($this->attributes($request, $data));
        $audit->log('pool.created', $pool, ['name' => $pool->name]);

        return redirect()
            ->route('pools.index')
            ->with('success', 'Pool created.');
    }

    public function edit(Pool $pool): Response
    {
        $connection = $pool->adminConnection();

        return Inertia::render('Pools/Form', [
            'pool' => [
                'id' => $pool->id,
                'name' => $pool->name,
                'kind' => $pool->kind,
                'engine' => $pool->engine,
                'runtime_version' => $pool->runtime_version,
                'server_id' => $pool->server_id,
                'capacity' => $pool->capacity,
                'dokploy_ref' => $pool->dokploy_ref,
                'host' => $connection['host'],
                'port' => $connection['port'],
                'mode' => $connection['mode'],
                'admin_username' => $connection['admin_username'],
                'admin_database' => $connection['admin_database'],
                'dokploy_environment_id' => $connection['dokploy_environment_id'],
                'has_admin_password' => filled($connection['admin_password']),
                'ssh_host' => $connection['ssh_host'],
                'ssh_port' => $connection['ssh_port'],
                'ssh_username' => $connection['ssh_username'],
                'has_ssh_key' => filled($connection['ssh_private_key']),
            ],
            'servers' => $this->servers(),
            'kinds' => $this->kinds(),
        ]);
    }

    public function update(Request $request, Pool $pool, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $pool->update($this->attributes($request, $data, $pool));
        $audit->log('pool.updated', $pool, ['name' => $pool->name]);

        return redirect()
            ->route('pools.index')
            ->with('success', 'Pool updated.');
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function servers(): array
    {
        return Server::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Server $server) => ['id' => $server->id, 'name' => $server->name])
            ->all();
    }

    /**
     * @return list<string>
     */
    private function kinds(): array
    {
        return ['database', 'storage', 'cache', 'runtime'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['required', Rule::in($this->kinds())],
            'engine' => ['nullable', 'string', 'max:255'],
            'runtime_version' => ['nullable', 'string', 'max:255'],
            'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'dokploy_ref' => ['nullable', 'string', 'max:255'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mode' => ['nullable', Rule::in(['shared', 'dedicated'])],
            'admin_username' => ['nullable', 'string', 'max:255'],
            'admin_password' => ['nullable', 'string', 'max:255'],
            'admin_database' => ['nullable', 'string', 'max:255'],
            'dokploy_environment_id' => ['nullable', 'string', 'max:255'],
            'ssh_host' => ['nullable', 'string', 'max:255'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['nullable', 'string', 'max:255'],
            'ssh_private_key' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(Request $request, array $data, ?Pool $pool = null): array
    {
        $current = $pool?->credentials ?? [];

        $credentials = array_filter([
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'admin_username' => $data['admin_username'] ?? null,
            'admin_password' => $request->filled('admin_password')
                ? $data['admin_password']
                : ($current['admin_password'] ?? null),
            'admin_database' => $data['admin_database'] ?? null,
            'dokploy_environment_id' => $data['dokploy_environment_id'] ?? null,
            'ssh_host' => $data['ssh_host'] ?? null,
            'ssh_port' => $data['ssh_port'] ?? null,
            'ssh_username' => $data['ssh_username'] ?? null,
            'ssh_private_key' => $request->filled('ssh_private_key')
                ? $data['ssh_private_key']
                : ($current['ssh_private_key'] ?? null),
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'engine' => $data['engine'] ?? null,
            'runtime_version' => $data['runtime_version'] ?? null,
            'server_id' => $data['server_id'] ?? null,
            'capacity' => $data['capacity'],
            'dokploy_ref' => $data['dokploy_ref'] ?? null,
            'meta' => array_filter([
                'host' => $data['host'] ?? null,
                'port' => $data['port'] ?? null,
                'mode' => $data['mode'] ?? 'shared',
            ], fn ($value) => $value !== null && $value !== ''),
            'credentials' => $credentials,
        ];
    }
}
