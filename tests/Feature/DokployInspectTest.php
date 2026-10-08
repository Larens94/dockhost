<?php


// DokployInspectTest.php — DokployInspectTest module.
//
// exports: DokployInspectTest | DokployInspectTest::test_inspect_endpoint_returns_grants_mismatch_from_dokploy_logs(): void | DokployInspectTest::test_inspect_command_prints_json_report(): void | DokployInspectTest::test_guests_cannot_inspect_dokploy_logs(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Feature;

use App\Models\Infrastructure;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DokployInspectTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_inspect_endpoint_returns_grants_mismatch_from_dokploy_logs(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->inspectFakes("GRANT_FAIL\nAccess denied for user 'root'@'10.0.1.76'"));

        $user = User::factory()->create();
        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deployed',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('infrastructures.dokploy-inspect', $infrastructure))
            ->assertOk()
            ->assertJsonPath('slug', 'infra1')
            ->assertJsonFragment(['code' => 'grants_root_mismatch']);

        $this->assertContains('mysql-grants', array_column($response->json('logs'), 'service'));

        Http::assertSent(fn (Request $request): bool => str_starts_with(
            $request->url(),
            'https://dokploy.test/api/compose.readLogs?',
        ));
    }

    public function test_inspect_command_prints_json_report(): void
    {
        Http::preventStrayRequests();
        Http::fake($this->inspectFakes("GRANT_OK\nFLUSH PRIVILEGES"));

        Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
            'status' => 'deployed',
        ]);

        $this->artisan('dokploy:inspect', ['slug' => 'infra1', '--json' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('grants_aligned');
    }

    public function test_guests_cannot_inspect_dokploy_logs(): void
    {
        $infrastructure = Infrastructure::factory()->create([
            'dokploy_compose_id' => 'compose-1',
        ]);

        $this->getJson(route('infrastructures.dokploy-inspect', $infrastructure))
            ->assertUnauthorized();
    }

    /**
     * @return array<string, PromiseInterface>
     */
    private function inspectFakes(string $grantsLog): array
    {
        return [
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                [
                    'deploymentId' => 'dep-1',
                    'status' => 'done',
                    'title' => 'Compose deploy',
                    'createdAt' => '2026-09-21T00:00:00.000Z',
                    'errorMessage' => null,
                ],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                [
                    'Id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'Name' => '/infra1-mariadb-1',
                    'State' => 'running',
                    'Status' => 'Up 2 minutes (healthy)',
                ],
                [
                    'Id' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    'Name' => '/infra1-mysql-grants-1',
                    'State' => 'exited',
                    'Status' => 'Exited (1) 10 seconds ago',
                ],
                [
                    'Id' => 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
                    'Name' => '/infra1-phpmyadmin-1',
                    'State' => 'running',
                    'Status' => 'Up 2 minutes',
                ],
                [
                    'Id' => 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd',
                    'Name' => '/infra1-postgres-1',
                    'State' => 'running',
                    'Status' => 'Up 2 minutes',
                ],
                [
                    'Id' => 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee',
                    'Name' => '/infra1-sftp-1',
                    'State' => 'running',
                    'Status' => 'Up 2 minutes',
                ],
            ]),
            'https://dokploy.test/api/compose.readLogs*' => function (Request $request) use ($grantsLog) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $containerId = $query['containerId'] ?? '';

                if ($containerId === 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb') {
                    return Http::response($grantsLog);
                }

                return Http::response('');
            },
        ];
    }
}
