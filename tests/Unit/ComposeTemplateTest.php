<?php

// ComposeTemplateTest.php — Unit coverage for ComposeTemplate render/env.
//
// exports: ComposeTemplateTest | ComposeTemplateTest::test_template_bakes_slug_and_sftp_port_into_yaml(): void | ComposeTemplateTest::test_isolated_networks_keep_databases_off_the_shared_network(): void | ComposeTemplateTest::test_two_slugs_produce_distinct_hostnames_and_ports(): void | ComposeTemplateTest::test_env_file_includes_slug_and_sftp_port(): void | ComposeTemplateTest::test_optional_phpmyadmin_can_be_omitted(): void | ComposeTemplateTest::test_optional_cache_storage_and_pgadmin_are_included_only_when_enabled(): void | ComposeTemplateTest::test_render_inlines_nonempty_postgres_and_colon_free_sftp_password(): void | ComposeTemplateTest::test_named_mariadb_volume_is_used_only_when_reset(): void | ComposeTemplateTest::test_phpmyadmin_hostname_uses_public_base(): void | ComposeTemplateTest::test_phpmyadmin_absolute_uri_requires_trailing_slash(): void
// used_by: none (PHPUnit entry)
// rules:   Default render() MUST put MariaDB on dokploy-network and MUST NOT emit name: {slug}-db.
//          test_isolated_networks_* documents UNUSED render(..., true) only — provisioners must not call it.
//          PMA_ABSOLUTE_URI must end with / or phpMyAdmin cookies fail behind Traefik.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Clarify default vs unused isolated mode
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_grants_pflag | Expect -p and $$ so Compose does not eat the password
//          composer-2.5-fast | cursor | 2026-10-10 | s_pma_uri_slash | Assert trailing slash Absolute URI
// message:

namespace Tests\Unit;

use App\Services\Infra\ComposeTemplate;
use Tests\TestCase;

class ComposeTemplateTest extends TestCase
{
    public function test_template_bakes_slug_and_sftp_port_into_yaml(): void
    {
        $yaml = (new ComposeTemplate)->render('infra2', 2223);

        $this->assertStringContainsString('dokploy-network', $yaml);
        $this->assertStringContainsString('infra2-mariadb', $yaml);
        $this->assertStringContainsString('infra2-sftp-sync', $yaml);
        $this->assertStringContainsString("\n  sftp-sync:\n", $yaml);
        $this->assertStringNotContainsString('container_name:', $yaml);
        $this->assertStringContainsString("aliases:\n          - infra2-mariadb", $yaml);
        $this->assertStringContainsString('name: infra2_data', $yaml);
        $this->assertStringNotContainsString('name: infra2_mariadb', $yaml);
        $this->assertStringNotContainsString('MARIADB_VOLUME_NAME', $yaml);
        $this->assertStringContainsString('"2223:22"', $yaml);
        $this->assertStringNotContainsString('${INFRA_SLUG}', $yaml);
        $this->assertStringNotContainsString('${SFTP_HOST_PORT', $yaml);
        $this->assertStringNotContainsString('infra1-mariadb', $yaml);
        $this->assertStringContainsString('isolated', strtolower($yaml));
        $this->assertStringNotContainsString('name: infra2-db', $yaml);
        $this->assertStringNotContainsString('__DB_NET__', $yaml);
        $this->assertStringNotContainsString("\n  redis:\n", $yaml);
        $this->assertStringNotContainsString("\n  minio:\n", $yaml);
        $this->assertStringNotContainsString("\n  pgadmin:\n", $yaml);
        $this->assertStringContainsString(
            'PMA_ABSOLUTE_URI: https://pma-infra2.cloud.silicoreautomation.com/',
            $yaml,
        );
    }

    public function test_isolated_networks_keep_databases_off_the_shared_network(): void
    {
        // Documents unused render(..., true) branch — InfrastructureProvisioner must pass false.
        $yaml = (new ComposeTemplate)->render('infra3', 2224, [
            'mariadb',
            'postgres',
            'sftp',
            'phpmyadmin',
            'pgadmin',
            'redis',
            'minio',
        ], [], true);

        $this->assertStringContainsString("name: infra3-db\n", $yaml);
        $this->assertStringContainsString("name: infra3-storage\n", $yaml);
        $this->assertStringContainsString("db:\n        aliases:\n          - infra3-mariadb", $yaml);
        $this->assertStringContainsString("db:\n        aliases:\n          - infra3-postgres", $yaml);
        $this->assertStringContainsString("db:\n        aliases:\n          - infra3-redis", $yaml);
        $this->assertStringContainsString("storage:\n        aliases:\n          - infra3-sftp-sync", $yaml);
        $this->assertStringContainsString("storage:\n        aliases:\n          - infra3-sftp", $yaml);
        $this->assertStringContainsString("dokploy-network:\n        aliases:\n          - infra3-phpmyadmin\n      db: {}", $yaml);
        $this->assertStringContainsString("dokploy-network:\n        aliases:\n          - infra3-pgadmin\n      db: {}", $yaml);
        $this->assertStringContainsString("dokploy-network:\n        aliases:\n          - infra3-minio\n      storage: {}", $yaml);
        preg_match('/  mariadb:\n(?:    .*\n)+?(?=\n  [a-z])/', $yaml, $mariadb);
        $this->assertNotEmpty($mariadb[0] ?? null);
        $this->assertStringNotContainsString('dokploy-network', (string) ($mariadb[0] ?? ''));
        preg_match('/  postgres:\n(?:    .*\n)+?(?=\n  [a-z])/', $yaml, $postgres);
        $this->assertStringNotContainsString('dokploy-network', (string) ($postgres[0] ?? ''));
        preg_match('/  redis:\n(?:    .*\n)+?(?=\n  [a-z])/', $yaml, $redis);
        $this->assertStringNotContainsString('dokploy-network', (string) ($redis[0] ?? ''));
        preg_match('/  sftp:\n(?:    .*\n)+?(?=\n  [a-z]|\nvolumes:)/', $yaml, $sftp);
        $this->assertStringNotContainsString('dokploy-network', (string) ($sftp[0] ?? ''));
    }

    public function test_two_slugs_produce_distinct_hostnames_and_ports(): void
    {
        $first = (new ComposeTemplate)->render('infra1', 2222);
        $second = (new ComposeTemplate)->render('infra2', 2223);

        $this->assertStringContainsString('hostname: infra1-mariadb', $first);
        $this->assertStringContainsString('hostname: infra2-mariadb', $second);
        $this->assertStringContainsString('"2222:22"', $first);
        $this->assertStringContainsString('"2223:22"', $second);
        $this->assertStringNotContainsString('infra2-mariadb', $first);
        $this->assertStringNotContainsString('"2222:22"', $second);
    }

    public function test_env_file_includes_slug_and_sftp_port(): void
    {
        $env = (new ComposeTemplate)->envFile('infra2', [
            'mysql_root_password' => 'root-secret',
            'mysql_app_password' => 'app-secret',
            'postgres_password' => 'pg-secret',
            'sftp_bootstrap_password' => 'sftp-secret',
            'sftp_sync_token' => 'synctokenvalue',
            'sftp_host_port' => 2223,
        ]);

        $this->assertStringContainsString("INFRA_SLUG=infra2\n", $env);
        $this->assertStringContainsString("SFTP_SYNC_TOKEN=synctokenvalue\n", $env);
        $this->assertStringContainsString("SFTP_HOST_PORT=2223\n", $env);
        $this->assertStringContainsString("MYSQL_APP_PASSWORD=app-secret\n", $env);
    }

    public function test_optional_phpmyadmin_can_be_omitted(): void
    {
        $yaml = (new ComposeTemplate)->render('infra1', 2222, ['mariadb', 'postgres', 'sftp']);

        $this->assertStringNotContainsString("\n  phpmyadmin:\n", $yaml);
        $this->assertStringContainsString("\n  mariadb:\n", $yaml);
        $this->assertStringContainsString("\n  sftp:\n", $yaml);
        $this->assertStringNotContainsString("\n  redis:\n", $yaml);
        $this->assertStringNotContainsString("\n  minio:\n", $yaml);
        $this->assertStringNotContainsString("\n  pgadmin:\n", $yaml);
    }

    public function test_optional_cache_storage_and_pgadmin_are_included_only_when_enabled(): void
    {
        $off = (new ComposeTemplate)->render('infra1', 2222);
        $on = (new ComposeTemplate)->render('infra1', 2222, [
            'mariadb',
            'postgres',
            'sftp',
            'phpmyadmin',
            'pgadmin',
            'redis',
            'minio',
        ], [
            'pgadmin_email' => 'admin@infra1.local',
            'pgadmin_password' => 'pgasecretvalue',
            'minio_root_user' => 'miniouser',
            'minio_root_password' => 'miniosecretvalue',
        ]);

        $this->assertStringNotContainsString("\n  redis:\n", $off);
        $this->assertStringContainsString("\n  redis:\n", $on);
        $this->assertStringContainsString('hostname: infra1-redis', $on);
        $this->assertStringNotContainsString('name: infra1_redis', $on);
        $this->assertStringContainsString('name: infra1_data', $on);
        $this->assertStringContainsString("\n  minio:\n", $on);
        $this->assertStringContainsString('image: quay.io/minio/minio', $on);
        $this->assertStringNotContainsString("image: minio/minio\n", $on);
        $this->assertStringContainsString('image: redis:7-alpine', $on);
        $this->assertStringContainsString('image: dpage/pgadmin4', $on);
        $this->assertStringContainsString('hostname: infra1-minio', $on);
        $this->assertStringContainsString('--console-address', $on);
        $this->assertStringContainsString("\n  pgadmin:\n", $on);
        $this->assertStringContainsString('PGADMIN_LISTEN_PORT: 80', $on);
        $this->assertStringContainsString('PGADMIN_DEFAULT_PASSWORD: pgasecretvalue', $on);
        $this->assertStringContainsString('MINIO_ROOT_PASSWORD: miniosecretvalue', $on);
    }

    public function test_render_inlines_nonempty_postgres_and_colon_free_sftp_password(): void
    {
        $secrets = [
            'mysql_root_password' => 'rootsecretvalue',
            'mysql_app_password' => 'appsecretvalue',
            'postgres_password' => 'pgsecretvalue',
            'sftp_bootstrap_password' => 'sftpsecretvalue',
            'sftp_sync_token' => 'synctokenvalue',
        ];

        $yaml = (new ComposeTemplate)->render('infra1', 2222, null, $secrets);
        $env = (new ComposeTemplate)->envFile('infra1', [
            ...$secrets,
            'sftp_host_port' => 2222,
        ]);

        $this->assertStringContainsString('POSTGRES_PASSWORD: pgsecretvalue', $yaml);
        $this->assertStringNotContainsString('${POSTGRES_PASSWORD}', $yaml);
        $this->assertStringContainsString('echo GRANT_OK', $yaml);
        $this->assertStringContainsString('echo GRANT_FAIL', $yaml);
        $this->assertStringContainsString('MYSQL_PWD: rootsecretvalue', $yaml);
        $this->assertStringContainsString('MYSQL_APP_PASSWORD: appsecretvalue', $yaml);
        $this->assertStringContainsString('mariadb --protocol=tcp -h"$$INFRA_SLUG-mariadb" -uroot -p"$$MYSQL_PWD"', $yaml);
        $this->assertStringContainsString('q "$$MYSQL_APP_PASSWORD"', $yaml);
        $this->assertStringNotContainsString('pid: service:mariadb', $yaml);
        $this->assertStringNotContainsString('ALTER USER \'root\'', $yaml);
        $this->assertStringNotContainsString("IDENTIFIED BY 'appsecretvalue'", $yaml);
        $this->assertStringNotContainsString("IDENTIFIED BY 'rootsecretvalue'", $yaml);
        $this->assertStringNotContainsString('mysql -h', $yaml);
        $this->assertStringContainsString('exec /entrypoint "infra:sftpsecretvalue:1001:1001"', $yaml);
        $this->assertStringContainsString("POSTGRES_PASSWORD=pgsecretvalue\n", $env);
        $this->assertStringContainsString("SFTP_BOOTSTRAP_PASSWORD=sftpsecretvalue\n", $env);
        $this->assertStringNotContainsString(':', $secrets['sftp_bootstrap_password']);
    }

    public function test_named_mariadb_volume_is_used_only_when_reset(): void
    {
        $named = (new ComposeTemplate)->render('infra1', 2222, null, [
            'mariadb_volume_name' => 'infra1_mariadb_v2',
        ]);

        $this->assertStringContainsString("  mariadb:\n    name: infra1_mariadb_v2\n", $named);
        $this->assertStringContainsString('MYSQL_ROOT_HOST: "%"', $named);
    }

    public function test_phpmyadmin_hostname_uses_public_base(): void
    {
        $this->assertSame(
            'pma-infra1.cloud.silicoreautomation.com',
            (new ComposeTemplate)->phpmyadminHostname('infra1'),
        );
        $this->assertSame(
            'pga-infra1.cloud.silicoreautomation.com',
            (new ComposeTemplate)->pgadminHostname('infra1'),
        );
        $this->assertSame(
            'minio-infra1.cloud.silicoreautomation.com',
            (new ComposeTemplate)->minioHostname('infra1'),
        );
    }

    public function test_phpmyadmin_absolute_uri_requires_trailing_slash(): void
    {
        $template = new ComposeTemplate;

        $this->assertSame(
            'https://pma-infra1.cloud.silicoreautomation.com/',
            $template->phpmyadminAbsoluteUri('infra1'),
        );
        $this->assertSame(
            'https://pma-custom.example.com/',
            $template->phpmyadminAbsoluteUri('infra1', 'pma-custom.example.com'),
        );
        $this->assertSame(
            'https://pma-custom.example.com/',
            $template->phpmyadminAbsoluteUri('infra1', 'https://pma-custom.example.com/'),
        );

        $yaml = $template->render('infra9', 2222, null, [
            'phpmyadmin_absolute_uri' => 'https://pma-infra9.example.com',
        ]);
        $this->assertStringContainsString(
            'PMA_ABSOLUTE_URI: https://pma-infra9.example.com/',
            $yaml,
        );
    }
}
