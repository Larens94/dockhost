<?php

// InfraDataNetworksTest.php — Unit docs for UNUSED InfraDataNetworks helper.
//
// exports: InfraDataNetworksTest | InfraDataNetworksTest::test_ensure_creates_overlay_networks_and_appends_panel_networks(): void
// used_by: none (PHPUnit entry)
// rules:   This covers an abandoned API. Provision/attach MUST NOT call ensure — shipped model is dokploy-network only.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Label as unused-mode coverage
// message:

namespace Tests\Unit;

use App\Services\Infra\InfraDataNetworks;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InfraDataNetworksTest extends TestCase
{
    public function test_ensure_creates_overlay_networks_and_appends_panel_networks(): void
    {
        config(['dokploy.self_application_id' => 'panel-app']);
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/network.all' => Http::response([]),
            'https://dokploy.test/api/network.create' => Http::sequence()
                ->push(['networkId' => 'net-db'])
                ->push(['networkId' => 'net-storage']),
            'https://dokploy.test/api/application.one*' => Http::response([
                'applicationId' => 'panel-app',
                'networkIds' => ['net-public'],
            ]),
            'https://dokploy.test/api/application.update' => Http::response(['ok' => true]),
        ]);

        $ids = app(InfraDataNetworks::class)->ensure('infra9');

        $this->assertSame([
            'db' => 'net-db',
            'storage' => 'net-storage',
        ], $ids);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/network.create'
            && $request['name'] === 'infra9-db'
            && $request['driver'] === 'overlay'
            && $request['attachable'] === true);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://dokploy.test/api/application.update'
            && $request['applicationId'] === 'panel-app'
            && $request['networkIds'] === ['net-public', 'net-db', 'net-storage']);
    }
}
