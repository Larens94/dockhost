<?php


// ComposeMysqlCredentialAlignerTest.php — ComposeMysqlCredentialAlignerTest module.
//
// exports: ComposeMysqlCredentialAlignerTest | ComposeMysqlCredentialAlignerTest::test_align_copies_compose_mysql_app_password_onto_the_panel_row(): void | ComposeMysqlCredentialAlignerTest::test_align_is_noop_when_passwords_already_match(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Unit;

use App\Services\Infra\ComposeMysqlCredentialAligner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComposeMysqlCredentialAlignerTest extends TestCase
{
    public function test_align_copies_compose_mysql_app_password_onto_the_panel_row(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'env' => "INFRA_SLUG=infra1\nMYSQL_APP_USER=infra\nMYSQL_APP_PASSWORD=livecomposepass\n",
            ]),
        ]);

        $infrastructure = $this->panelInfrastructure([
            'dokploy_compose_id' => 'compose-1',
            'mysql_admin_password' => 'stale-panel-pass',
        ]);

        $changed = app(ComposeMysqlCredentialAligner::class)->align($infrastructure);

        $this->assertTrue($changed);
        $this->assertSame('livecomposepass', $infrastructure->refresh()->mysql_admin_password);
        $this->assertSame('infra', $infrastructure->mysql_admin_user);
    }

    public function test_align_is_noop_when_passwords_already_match(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://dokploy.test/api/compose.one*' => Http::response([
                'composeId' => 'compose-1',
                'env' => "MYSQL_APP_USER=infra\nMYSQL_APP_PASSWORD=secretmysqlapp\n",
            ]),
        ]);

        $infrastructure = $this->panelInfrastructure([
            'dokploy_compose_id' => 'compose-1',
            'mysql_admin_password' => 'secretmysqlapp',
        ]);

        $this->assertFalse(app(ComposeMysqlCredentialAligner::class)->align($infrastructure));
    }
}
