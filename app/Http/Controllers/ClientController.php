<?php

// ClientController.php — Client anagrafica CRUD (superadmin).
//
// exports: ClientController | ClientController::create(): Response | ClientController::store(Request $request): RedirectResponse | ClientController::edit(Client $client): Response | ClientController::update(Request $request, Client $client): RedirectResponse
// used_by: routes/web.php
// rules:   superadmin panel only — no customer portal; do not expose billing_status mutation here (billing owns that)
// agent:   composer | cursor | 2026-09-18 | s_20260918_client_crud | Client create/edit CRUD for anagrafica
// message: Index stays on PanelController::clients; this controller owns create/store/edit/update only.

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\AuditLogger;
use App\Services\SiteLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    /**
     * Show create-client form.
     *
     * Rules:   Superadmin anagrafica only — no customer self-service.
     */
    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Persist a new client.
     *
     * Rules:   billing_status defaults to none — never invent Stripe state here.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $client = Client::query()->create([
            ...$data,
            'billing_status' => 'none',
        ]);

        app(AuditLogger::class)->log('client.created', $client, ['name' => $client->name]);

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client created.');
    }

    /**
     * Show edit-client form.
     *
     * Rules:   Route-model binding; do not leak stripe_customer_id to the form.
     */
    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'email' => $client->email,
                'billing_email' => $client->billing_email,
                'status' => $client->status,
            ],
            'statuses' => $this->statuses(),
        ]);
    }

    /**
     * Update an existing client.
     *
     * Rules:   Do not touch stripe_customer_id or billing_status from this form.
     */
    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validated($request, $client);

        $client->update($data);

        app(AuditLogger::class)->log('client.updated', $client, ['name' => $client->name]);

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client updated.');
    }

    /**
     * Remove a client after its sites are deprovisioned.
     *
     * Rules:   Release pool capacity before the client row disappears.
     */
    public function destroy(Client $client, SiteLifecycle $lifecycle, AuditLogger $audit): RedirectResponse
    {
        foreach ($client->sites()->get() as $site) {
            $lifecycle->deprovision($site);
        }

        $name = $client->name;
        $clientId = $client->id;
        $client->delete();
        $audit->log('client.deleted', null, ['name' => $name, 'client_id' => $clientId]);

        return redirect()
            ->route('clients.index')
            ->with('success', 'Client deleted.');
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return ['active', 'suspended', 'archived'];
    }

    /**
     * @return array{name: string, company: ?string, email: ?string, billing_email: ?string, status: string}
     */
    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->ignore($client?->id),
            ],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in($this->statuses())],
        ]);
    }
}
