<?php

// MysqlProvisionerGrantTest.php — Asserts site GRANT is scoped to one database.
//
// exports: MysqlProvisionerGrantTest | MysqlProvisionerGrantTest::test_grant_user_issues_select_on_existing_database(): void | MysqlProvisionerGrantTest::test_grant_user_issues_all_privileges(): void
// used_by: none (PHPUnit entry)
// rules:   Statements MUST be GRANT … ON `db`.* TO `user`@'%' — never *.* for site users.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Codify one-database GRANT expectation
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_db_pass | Expect ALTER USER so an existing password is replaced
//          composer-2.5-fast | cursor | 2026-10-09 | s_db_user_replace | DROP/CREATE user for reliable phpMyAdmin auth
// message:

namespace Tests\Unit;

use App\Enums\DatabasePrivilege;
use App\Models\Infrastructure;
use App\Services\Infra\MysqlProvisioner;
use Mockery;
use PDO;
use Tests\TestCase;

class MysqlProvisionerGrantTest extends TestCase
{
    public function test_grant_user_issues_select_on_existing_database(): void
    {
        $statements = [];
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('quote')->andReturnUsing(fn (string $value): string => "'".$value."'");
        $pdo->shouldReceive('exec')->andReturnUsing(function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 1;
        });

        $infrastructure = Infrastructure::factory()->make(['slug' => 'infra1', 'mysql_host' => 'infra1-mariadb']);
        $provisioner = new MysqlProvisioner(
            fn (): PDO => $pdo,
            null,
            fn (): PDO => $pdo,
        );
        $provisioner->grantUser('d_shop', 'u_reader', 'secretpass', DatabasePrivilege::Select, $infrastructure);

        $this->assertContains("DROP USER IF EXISTS `u_reader`@'%'", $statements);
        $this->assertContains('SET old_passwords=0', $statements);
        $this->assertContains(
            "CREATE USER `u_reader`@'%' IDENTIFIED BY 'secretpass'",
            $statements,
        );
        $this->assertContains(
            "ALTER USER `u_reader`@'%' IDENTIFIED BY 'secretpass'",
            $statements,
        );
        $this->assertContains("GRANT SELECT ON `d_shop`.* TO `u_reader`@'%'", $statements);
        $this->assertContains('FLUSH PRIVILEGES', $statements);
        $this->assertFalse(
            collect($statements)->contains(fn (string $sql): bool => str_contains($sql, 'CREATE DATABASE')),
        );
    }

    public function test_grant_user_issues_all_privileges(): void
    {
        $statements = [];
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('quote')->andReturnUsing(fn (string $value): string => "'".$value."'");
        $pdo->shouldReceive('exec')->andReturnUsing(function (string $sql) use (&$statements): int {
            $statements[] = $sql;

            return 1;
        });

        $infrastructure = Infrastructure::factory()->make(['slug' => 'infra1', 'mysql_host' => 'infra1-mariadb']);
        $provisioner = new MysqlProvisioner(
            fn (): PDO => $pdo,
            null,
            fn (): PDO => $pdo,
        );
        $provisioner->grantUser('d_shop', 'u_owner', 'secretpass', DatabasePrivilege::All, $infrastructure);

        $this->assertContains("GRANT ALL PRIVILEGES ON `d_shop`.* TO `u_owner`@'%'", $statements);
    }
}
