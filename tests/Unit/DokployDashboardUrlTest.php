<?php

// DokployDashboardUrlTest.php — DokployDashboardUrlTest module.
//
// exports: DokployDashboardUrlTest | DokployDashboardUrlTest::test_it_builds_project_and_compose_dashboard_urls_from_known_ids(): void | DokployDashboardUrlTest::test_application_url_uses_infrastructure_project_and_environment(): void | DokployDashboardUrlTest::test_it_returns_null_when_ids_or_base_url_are_missing(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace Tests\Unit;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployDashboardUrl;
use Tests\TestCase;

class DokployDashboardUrlTest extends TestCase
{
    public function test_it_builds_project_and_compose_dashboard_urls_from_known_ids(): void
    {
        config(['dokploy.url' => 'https://cloud.silicoreautomation.com']);

        $urls = new DokployDashboardUrl;

        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd',
            $urls->projectEnvironment('sRIjweF4hU8cxXJvlXuWb', '5NtlFVpJWfLe-wck_qrKd'),
        );
        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd/services/compose/PBPmVaxW3S7lc2hyoPDvF',
            $urls->compose('sRIjweF4hU8cxXJvlXuWb', '5NtlFVpJWfLe-wck_qrKd', 'PBPmVaxW3S7lc2hyoPDvF'),
        );
    }

    public function test_application_url_uses_infrastructure_project_and_environment(): void
    {
        config([
            'dokploy.url' => 'https://cloud.silicoreautomation.com',
            'dokploy.environment_id' => 'panel-env-must-not-be-used',
        ]);

        $infrastructure = Infrastructure::factory()->make([
            'dokploy_project_id' => 'sRIjweF4hU8cxXJvlXuWb',
            'dokploy_environment_id' => '5NtlFVpJWfLe-wck_qrKd',
            'dokploy_compose_id' => 'PBPmVaxW3S7lc2hyoPDvF',
        ]);

        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd',
            $infrastructure->dokployProjectUrl(),
        );
        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd/services/compose/PBPmVaxW3S7lc2hyoPDvF',
            $infrastructure->dokployComposeUrl(),
        );
        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd/services/application/app-laravel-1',
            $infrastructure->dokployApplicationUrl('app-laravel-1'),
        );
        $this->assertSame(
            'https://cloud.silicoreautomation.com/dashboard/project/sRIjweF4hU8cxXJvlXuWb/environment/5NtlFVpJWfLe-wck_qrKd/services/application/app-laravel-1?tab=general',
            app(DokployDashboardUrl::class)->applicationGeneralTab(
                'sRIjweF4hU8cxXJvlXuWb',
                '5NtlFVpJWfLe-wck_qrKd',
                'app-laravel-1',
            ),
        );
        $this->assertStringNotContainsString('panel-env-must-not-be-used', (string) $infrastructure->dokployApplicationUrl('app-laravel-1'));
    }

    public function test_it_returns_null_when_ids_or_base_url_are_missing(): void
    {
        config(['dokploy.url' => '']);

        $urls = new DokployDashboardUrl;

        $this->assertNull($urls->projectEnvironment('proj', 'env'));

        config(['dokploy.url' => 'https://dokploy.test']);

        $this->assertNull($urls->projectEnvironment(null, 'env'));
        $this->assertNull($urls->compose('proj', 'env', null));
        $this->assertNull($urls->application('proj', 'env', ''));
    }
}
