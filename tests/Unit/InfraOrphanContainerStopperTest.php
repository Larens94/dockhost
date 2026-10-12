<?php

// InfraOrphanContainerStopperTest.php — Unit coverage for slug DNS orphan container detection/stop.
//
// exports: InfraOrphanContainerStopperTest
// used_by: none (PHPUnit entry)
// rules:   Legacy infra1-mariadb and prior appName suffixes must be orphans; current compose appName must not.
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_orphan_dns | Detect/stop dokploy-network DNS collisions
// message:

namespace Tests\Unit;

use App\Services\Dokploy\DokployClient;
use App\Services\Infra\InfraOrphanContainerStopper;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InfraOrphanContainerStopperTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    #[DataProvider('orphanDetectionProvider')]
    public function test_detects_orphan_containers_for_slug(
        string $containerName,
        string $slug,
        ?string $keepComposeAppName,
        bool $expectedOrphan,
    ): void {
        $stopper = new InfraOrphanContainerStopper(Mockery::mock(DokployClient::class));

        $this->assertSame(
            $expectedOrphan,
            $stopper->isOrphanForSlug($containerName, $slug, $keepComposeAppName),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string, 3: bool}>
     */
    public static function orphanDetectionProvider(): array
    {
        return [
            'legacy hostname container' => ['infra1-mariadb', 'infra1', 'infra1-gj0cip', true],
            'legacy phpmyadmin' => ['infra1-phpmyadmin', 'infra1', 'infra1-gj0cip', true],
            'legacy postgres' => ['infra1-postgres', 'infra1', 'infra1-gj0cip', true],
            'prior compose suffix' => ['infra1-itxmbo-mariadb-1', 'infra1', 'infra1-gj0cip', true],
            'current compose kept' => ['infra1-gj0cip-mariadb-1', 'infra1', 'infra1-gj0cip', false],
            'current phpmyadmin kept' => ['infra1-gj0cip-phpmyadmin-1', 'infra1', 'infra1-gj0cip', false],
            'other infra ignored' => ['infra2-mariadb', 'infra1', 'infra1-gj0cip', false],
            'site app ignored' => ['vibesbridge-com-ivevh2.1.abc', 'infra1', 'infra1-gj0cip', false],
            'panel db ignored' => ['internal-application-dokhostsdb-6uxojo.1.abc', 'infra1', null, false],
            'purge stops all slug services' => ['infra1-gj0cip-mariadb-1', 'infra1', null, true],
            'leading slash normalized' => ['/infra1-mariadb', 'infra1', 'infra1-gj0cip', true],
            'sftp-sync not confused with sftp' => ['infra1-sftp-sync', 'infra1', 'infra1-gj0cip', true],
        ];
    }

    public function test_stop_for_slug_stops_only_running_orphans(): void
    {
        $dokploy = Mockery::mock(DokployClient::class);
        $dokploy->shouldReceive('listContainers')->once()->andReturn([
            [
                'containerId' => 'ghost-maria',
                'name' => 'infra1-mariadb',
                'state' => 'running',
                'status' => 'Up 3 weeks',
            ],
            [
                'containerId' => 'current-maria',
                'name' => 'infra1-gj0cip-mariadb-1',
                'state' => 'running',
                'status' => 'Up 2 weeks',
            ],
            [
                'containerId' => 'ghost-exited',
                'name' => 'infra1-phpmyadmin',
                'state' => 'exited',
                'status' => 'Exited (0)',
            ],
        ]);
        $dokploy->shouldReceive('stopContainer')->once()->with('ghost-maria')->andReturn([]);

        $stopper = new InfraOrphanContainerStopper($dokploy);

        $this->assertSame(1, $stopper->stopForSlug('infra1', 'infra1-gj0cip'));
    }
}
