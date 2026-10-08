<?php


// MysqlProvisionerConnectErrorTest.php — MysqlProvisionerConnectErrorTest module.
//
// exports: MysqlProvisionerConnectErrorTest | MysqlProvisionerConnectErrorTest::test_access_denied_becomes_italian_validation_exception(): void | MysqlProvisionerConnectErrorTest::test_retries_after_aligning_compose_password(): void
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Tests\Unit;

use App\Services\Infra\ComposeMysqlCredentialAligner;
use App\Services\Infra\MysqlProvisioner;
use Illuminate\Validation\ValidationException;
use Mockery;
use PDO;
use PDOException;
use Tests\TestCase;

class MysqlProvisionerConnectErrorTest extends TestCase
{
    public function test_access_denied_becomes_italian_validation_exception(): void
    {
        $infrastructure = $this->panelInfrastructure();
        $aligner = Mockery::mock(ComposeMysqlCredentialAligner::class);
        $aligner->shouldReceive('align')->once()->andReturn(false);

        $provisioner = new MysqlProvisioner(
            fn (): PDO => Mockery::mock(PDO::class),
            $aligner,
            function (): PDO {
                throw new PDOException("SQLSTATE[HY000] [1045] Access denied for user 'infra'@'10.0.1.96' (using password: YES)");
            },
        );

        try {
            $provisioner->provision('d_shop', 'u_shop', 'secretpass', $infrastructure);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('create_database', $exception->errors());
            $this->assertStringContainsString('Impossibile creare il database su infra1', $exception->errors()['create_database'][0]);
            $this->assertStringContainsString('MariaDB ha rifiutato', $exception->errors()['create_database'][0]);
        }
    }

    public function test_retries_after_aligning_compose_password(): void
    {
        $infrastructure = $this->panelInfrastructure([
            'mysql_admin_password' => 'stale-panel-pass',
        ]);
        $aligner = Mockery::mock(ComposeMysqlCredentialAligner::class);
        $aligner->shouldReceive('align')->once()->andReturnUsing(function () use ($infrastructure): bool {
            $infrastructure->update(['mysql_admin_password' => 'livecomposepass']);

            return true;
        });

        $attempts = [];
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('quote')->andReturnUsing(fn (string $value): string => "'".$value."'");
        $pdo->shouldReceive('exec')->andReturn(1);

        $provisioner = new MysqlProvisioner(
            fn (): PDO => $pdo,
            $aligner,
            function (string $dsn, string $user, string $password) use (&$attempts, $pdo): PDO {
                $attempts[] = $password;
                if ($password === 'stale-panel-pass') {
                    throw new PDOException("SQLSTATE[HY000] [1045] Access denied for user 'infra'@'10.0.1.96'");
                }

                return $pdo;
            },
        );

        $provisioner->provision('d_shop', 'u_shop', 'secretpass', $infrastructure);

        $this->assertSame(['stale-panel-pass', 'livecomposepass'], $attempts);
    }
}
