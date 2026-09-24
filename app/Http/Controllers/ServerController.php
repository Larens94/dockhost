<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $server = Server::query()->create($data);
        $audit->log('server.created', $server, ['name' => $server->name]);

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server created.');
    }

    public function update(Request $request, Server $server, AuditLogger $audit): RedirectResponse
    {
        $server->update($this->validated($request));
        $audit->log('server.updated', $server, ['name' => $server->name]);

        return redirect()
            ->route('servers.index')
            ->with('success', 'Server updated.');
    }

    /**
     * @return array{name: string, ip: ?string, role: string, status: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip' => ['nullable', 'string', 'max:255'],
            'role' => ['required', Rule::in(['worker', 'database', 'storage', 'edge'])],
            'status' => ['required', Rule::in(['online', 'offline', 'maintenance'])],
        ]);
    }
}
