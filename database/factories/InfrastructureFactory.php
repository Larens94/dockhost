<?php


// InfrastructureFactory.php — InfrastructureFactory module.
//
// exports: InfrastructureFactory | InfrastructureFactory::configure(): static | InfrastructureFactory::definition(): array
// used_by: none
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message: 

namespace Database\Factories;

use App\Models\Infrastructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Infrastructure>
 */
class InfrastructureFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Infrastructure $infrastructure): void {
            if (filled($infrastructure->sftp_host_port) || ! filled($infrastructure->slug)) {
                return;
            }

            if (preg_match('/(\d+)$/', (string) $infrastructure->slug, $matches) === 1) {
                $infrastructure->sftp_host_port = 2221 + max((int) $matches[1], 1);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = 'infra'.fake()->unique()->numberBetween(1, 99);

        return [
            'slug' => $slug,
            'name' => 'Infra '.$slug,
            'template' => 'base',
            'enabled_services' => [
                'mariadb',
                'mysql-grants',
                'postgres',
                'phpmyadmin',
                'sftp-users-init',
                'sftp-sync',
                'sftp',
            ],
            'dokploy_project_id' => 'proj-'.fake()->unique()->numerify('####'),
            'dokploy_environment_id' => 'env-'.fake()->unique()->numerify('####'),
            'dokploy_compose_id' => 'compose-'.fake()->unique()->numerify('####'),
            'status' => 'deployed',
            'mysql_host' => $slug.'-mariadb',
            'mysql_port' => 3306,
            'mysql_admin_user' => 'infra',
            'mysql_admin_password' => 'secretmysqlapp',
            'mysql_root_password' => 'secretmysqlroot',
            'postgres_host' => $slug.'-postgres',
            'postgres_port' => 5432,
            'postgres_admin_user' => 'infra',
            'postgres_admin_password' => 'secretpostgres',
            'postgres_admin_database' => 'postgres',
            'sftp_host' => $slug.'-sftp',
            'sftp_host_port' => null,
            'sftp_bootstrap_password' => 'secretsftpuser',
            'sftp_sync_token' => 'testsftpsynctoken',
            'phpmyadmin_domain' => null,
            'pgadmin_domain' => null,
            'minio_domain' => null,
            'pgadmin_email' => null,
            'pgadmin_password' => null,
            'minio_root_user' => null,
            'minio_root_password' => null,
            'redis_host' => null,
            'minio_host' => null,
            'storage_root' => '/data/'.$slug,
            'sftp_users_file' => '/etc/sftp/'.$slug.'/users.conf',
            'panel_volumes_attached' => false,
        ];
    }
}
