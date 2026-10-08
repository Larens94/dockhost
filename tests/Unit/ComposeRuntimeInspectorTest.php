<?php

// ComposeRuntimeInspectorTest.php — ComposeRuntimeInspectorTest module.
//
// exports: ComposeRuntimeInspectorTest | ComposeRuntimeInspectorTest::test_inspect_marks_grants_aligned_when_log_contains_grant_ok(): void | ComposeRuntimeInspectorTest::test_inspect_marks_grants_failed_when_grant_fail_follows_grant_ok(): void
// used_by: none
// rules:   A log that contains GRANT_FAIL is a mismatch even when GRANT_OK appeared earlier.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Unit;

use App\Models\Infrastructure;
use App\Services\Infra\ComposeRuntimeInspector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComposeRuntimeInspectorTest extends TestCase
{
    public function test_inspect_marks_grants_aligned_when_log_contains_grant_ok(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                ['status' => 'done', 'title' => 'Compose deploy', 'createdAt' => '2026-09-21T00:00:00.000Z'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Id' => 'mariadbid01mariadbid01mariadbid01mariadbid01mariadbid01maria', 'Name' => '/infra1-mariadb-1', 'State' => 'running', 'Status' => 'Up 1 minute (healthy)'],
                ['Id' => 'grantsid01grantsid01grantsid01grantsid01grantsid01grantsid01gran', 'Name' => '/infra1-mysql-grants-1', 'State' => 'exited', 'Status' => 'Exited (0) 5 seconds ago'],
                ['Id' => 'pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01p', 'Name' => '/infra1-phpmyadmin-1', 'State' => 'running', 'Status' => 'Up 1 minute'],
            ]),
            'https://dokploy.test/api/compose.readLogs*' => Http::response('GRANT_START\nGRANT_OK'),
        ]);

        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
        ]);

        $report = app(ComposeRuntimeInspector::class)->inspect($infrastructure);

        $this->assertContains('grants_aligned', array_column($report['findings'], 'code'));
        $this->assertSame('mysql-grants', $report['containers'][1]['service'] ?? null);
    }

    public function test_inspect_marks_grants_failed_when_grant_fail_follows_grant_ok(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'appName' => 'infra1',
                'composeStatus' => 'done',
            ]),
            'https://dokploy.test/api/deployment.allByCompose*' => Http::response([
                ['status' => 'done', 'title' => 'Compose deploy', 'createdAt' => '2026-09-21T00:00:00.000Z'],
            ]),
            'https://dokploy.test/api/docker.getContainersByAppNameMatch*' => Http::response([
                ['Id' => 'mariadbid01mariadbid01mariadbid01mariadbid01mariadbid01maria', 'Name' => '/infra1-mariadb-1', 'State' => 'running', 'Status' => 'Up 1 minute (healthy)'],
                ['Id' => 'grantsid01grantsid01grantsid01grantsid01grantsid01grantsid01gran', 'Name' => '/infra1-mysql-grants-1', 'State' => 'exited', 'Status' => 'Exited (1) 5 seconds ago'],
                ['Id' => 'pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01pmaid01p', 'Name' => '/infra1-phpmyadmin-1', 'State' => 'running', 'Status' => 'Up 1 minute'],
            ]),
            'https://dokploy.test/api/compose.readLogs*' => Http::response("GRANT_START\nGRANT_OK\nGRANT_START\nGRANT_FAIL\n"),
        ]);

        $infrastructure = Infrastructure::factory()->create([
            'slug' => 'infra1',
            'dokploy_compose_id' => 'compose-1',
        ]);

        $report = app(ComposeRuntimeInspector::class)->inspect($infrastructure);
        $codes = array_column($report['findings'], 'code');

        $this->assertContains('grants_root_mismatch', $codes);
        $this->assertNotContains('grants_aligned', $codes);
    }
}
