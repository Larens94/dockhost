<?php

// InfrastructureProvisioner.php — Creates/updates one Dokploy compose per infra slug.
//
// exports: InfrastructureProvisioner | InfrastructureProvisioner::provision(array $attributes): Infrastructure | InfrastructureProvisioner::destroy(Infrastructure $infrastructure): void | InfrastructureProvisioner::updateStack(Infrastructure $infrastructure, ?array $enabledServices = null): Infrastructure | InfrastructureProvisioner::resetMysqlDatadir(Infrastructure $infrastructure): Infrastructure | InfrastructureProvisioner::attachPhpmyadminDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure | InfrastructureProvisioner::attachPgadminDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure | InfrastructureProvisioner::attachMinioDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure | InfrastructureProvisioner::refreshDeployStatus(Infrastructure $infrastructure): array
// used_by: app/Http/Controllers/InfrastructureController.php
// rules:   One infra slug = one Dokploy compose; ALL services on shared dokploy-network (aliases ${slug}-mariadb etc.).
//          NEVER call InfraDataNetworks::ensure; NEVER render ComposeTemplate with isolatedNetworks=true.
//          New rows MUST keep isolated_networks=false (column may remain). Do NOT delete live infras from here casually.
//          Never print DB/SFTP secrets. Site DB users are created elsewhere with GRANT on one DB only — not *.* .
//          generateSecret() is [A-Za-z0-9] only so Compose and the shell cannot expand $ in a password.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Revert create/updateStack to shared dokploy-network only
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_mysql_grants | Compose secrets stay alphanumeric so grants cannot rewrite them
//          composer-2.5-fast | cursor | 2026-10-10 | s_pma_uri_slash | Bake phpMyAdmin Absolute URI from phpmyadmin_domain
// message: Isolated-network split was a misunderstanding; Fabrizio recreates stacks on Dokploy after deploy.

namespace App\Services\Infra;

use App\Models\Infrastructure;
use App\Services\Dokploy\DokployClient;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class InfrastructureProvisioner
{
    public function __construct(
        private DokployClient $dokploy,
        private ComposeTemplate $composeTemplate,
        private ComposeDeployStatus $composeDeployStatus,
    ) {}

    /**
     * @param  array{slug: string, name?: string, template?: string, enabled_services?: list<string>}  $attributes
     */
    public function provision(array $attributes): Infrastructure
    {
        $slug = Str::lower($attributes['slug']);
        $name = filled($attributes['name'] ?? null) ? (string) $attributes['name'] : $slug;
        $template = $attributes['template'] ?? 'base';
        $enabledServices = $this->composeTemplate->normalizeEnabled($attributes['enabled_services'] ?? null);

        $sftpHostPort = $this->sftpHostPort($slug);
        $secrets = [
            'mysql_root_password' => $this->generateSecret(32),
            'mysql_app_password' => $this->generateSecret(24),
            'postgres_password' => $this->generateSecret(32),
            'sftp_bootstrap_password' => $this->generateSecret(24),
            'sftp_sync_token' => $this->generateSecret(32),
            'sftp_host_port' => $sftpHostPort,
            'pgadmin_email' => $this->composeTemplate->pgadminEmail($slug),
            'pgadmin_password' => $this->generateSecret(24),
            'minio_root_user' => $this->generateSecret(16),
            'minio_root_password' => $this->generateSecret(32),
        ];

        // Rules: always shared dokploy-network — never pass isolatedNetworks=true on create.
        $composeFile = $this->composeTemplate->render($slug, $sftpHostPort, $enabledServices, $secrets, false);

        $infrastructure = Infrastructure::query()->create([
            'slug' => $slug,
            'name' => $name,
            'template' => $template,
            'enabled_services' => $enabledServices,
            'dokploy_project_id' => null,
            'dokploy_environment_id' => '',
            'dokploy_compose_id' => null,
            'status' => 'pending',
            'mysql_host' => $slug.'-mariadb',
            'mysql_port' => 3306,
            'mysql_admin_user' => 'infra',
            'mysql_admin_password' => $secrets['mysql_app_password'],
            'mysql_root_password' => $secrets['mysql_root_password'],
            'postgres_host' => $slug.'-postgres',
            'postgres_port' => 5432,
            'postgres_admin_user' => 'infra',
            'postgres_admin_password' => $secrets['postgres_password'],
            'postgres_admin_database' => 'postgres',
            'sftp_host' => $slug.'-sftp',
            'sftp_host_port' => $sftpHostPort,
            'sftp_bootstrap_password' => $secrets['sftp_bootstrap_password'],
            'sftp_sync_token' => $secrets['sftp_sync_token'],
            'phpmyadmin_domain' => null,
            'pgadmin_domain' => null,
            'minio_domain' => null,
            'pgadmin_email' => $secrets['pgadmin_email'],
            'pgadmin_password' => $secrets['pgadmin_password'],
            'minio_root_user' => $secrets['minio_root_user'],
            'minio_root_password' => $secrets['minio_root_password'],
            'redis_host' => in_array('redis', $enabledServices, true) ? $slug.'-redis' : null,
            'minio_host' => in_array('minio', $enabledServices, true) ? $slug.'-minio' : null,
            'storage_root' => '/data',
            'sftp_users_file' => '/etc/sftp/users.conf',
            'panel_volumes_attached' => false,
            'isolated_networks' => false,
            'last_error' => null,
            'panel_volumes_error' => null,
        ]);

        try {
            $this->purgeDokploySlug($slug);

            $createdProject = $this->dokploy->createProject([
                'name' => $slug,
            ]);

            $projectId = $this->dokploy->idFrom($createdProject, 'projectId');
            $environmentId = $this->environmentIdForProject($createdProject, $projectId);

            $infrastructure->update([
                'dokploy_project_id' => $projectId,
                'dokploy_environment_id' => $environmentId,
            ]);

            $created = $this->dokploy->createCompose([
                'name' => $slug,
                'appName' => $slug,
                'environmentId' => $environmentId,
                'composeType' => 'docker-compose',
                'sourceType' => 'raw',
                'composeFile' => $composeFile,
            ]);

            $composeId = $this->dokploy->idFrom($created, 'composeId');

            $infrastructure->update([
                'dokploy_compose_id' => $composeId,
            ]);

            $this->pushCompose($composeId, $composeFile, $this->composeTemplate->envFile($slug, $secrets));

            $this->markDeploying($infrastructure);
            $this->composeDeployStatus->sync($infrastructure);
        } catch (Throwable $exception) {
            $this->failInfrastructure($infrastructure, $exception);
        }

        $this->attachMissingPublicDomains($infrastructure);

        return $infrastructure->refresh();
    }

    /**
     * Remove every Dokploy project/compose named like this slug, including leftover
     * stacks from previous recreates, and drop their Docker volumes.
     */
    public function destroy(Infrastructure $infrastructure): void
    {
        $this->purgeDokploySlug(
            $infrastructure->slug,
            $infrastructure->dokploy_project_id,
            $infrastructure->dokploy_compose_id,
        );

        $infrastructure->delete();
    }

    private function purgeDokploySlug(string $slug, ?string $knownProjectId = null, ?string $knownComposeId = null): void
    {
        $projectIds = [];
        $composeIds = [];

        if (is_string($knownProjectId) && $knownProjectId !== '') {
            $projectIds[] = $knownProjectId;
        }

        if (is_string($knownComposeId) && $knownComposeId !== '') {
            $composeIds[] = $knownComposeId;
        }

        try {
            foreach ($this->dokploy->allProjects() as $project) {
                if (! is_array($project)) {
                    continue;
                }

                $name = strtolower((string) ($project['name'] ?? ''));
                $projectId = $project['projectId'] ?? $project['id'] ?? null;

                if ($name !== strtolower($slug) || ! is_string($projectId) || $projectId === '') {
                    continue;
                }

                $projectIds[] = $projectId;
            }
        } catch (Throwable) {
        }

        $projectIds = array_values(array_unique($projectIds));

        foreach ($projectIds as $projectId) {
            try {
                $composeIds = [
                    ...$composeIds,
                    ...$this->dokploy->composeIdsFromProject($this->dokploy->getProject($projectId)),
                ];
            } catch (Throwable) {
            }
        }

        $composeIds = array_values(array_unique($composeIds));

        foreach ($composeIds as $composeId) {
            $this->dokployCleanup(fn () => $this->dokploy->stopCompose([
                'composeId' => $composeId,
            ]));
            $this->dokployCleanup(fn () => $this->dokploy->deleteCompose([
                'composeId' => $composeId,
                'deleteVolumes' => true,
            ]));
        }

        foreach ($projectIds as $projectId) {
            $this->dokployCleanup(fn () => $this->dokploy->deleteProject([
                'projectId' => $projectId,
            ]));
        }

        $this->dokployCleanup(fn () => $this->dokploy->cleanUnusedVolumes());
    }

    /**
     * @param  callable(): mixed  $operation
     */
    private function dokployCleanup(callable $operation): void
    {
        try {
            $operation();
        } catch (Throwable $exception) {
            $message = strtolower($this->dokploy->errorMessage($exception));

            if (str_contains($message, 'not found')
                || str_contains($message, 'already')
                || str_contains($message, 'unauthorized')
                || str_contains($message, 'forbidden')
                || str_contains($message, 'insufficient')) {
                return;
            }

            throw $exception;
        }
    }

    /**
     * Push the current template (and optional service selection) without rotating existing secrets.
     *
     * @param  list<string>|null  $enabledServices
     */
    public function updateStack(Infrastructure $infrastructure, ?array $enabledServices = null): Infrastructure
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Only infrastructures created from this panel can be updated here.');
        }

        $normalized = $this->composeTemplate->normalizeEnabled(
            $enabledServices ?? $infrastructure->enabled_services,
        );
        $sftpHostPort = (int) ($infrastructure->sftp_host_port ?: $this->sftpHostPort($infrastructure->slug));
        [$secrets, $persist] = $this->secretsFor($infrastructure, $sftpHostPort);

        // Rules: never ensure {slug}-db/{slug}-storage; always re-push shared dokploy-network compose.
        $composeFile = $this->composeTemplate->render(
            $infrastructure->slug,
            $sftpHostPort,
            $normalized,
            $secrets,
            false,
        );

        $this->pushCompose(
            $composeId,
            $composeFile,
            $this->composeTemplate->envFile($infrastructure->slug, $secrets),
        );

        $infrastructure->update([
            ...$persist,
            'enabled_services' => $normalized,
            'sftp_host_port' => $sftpHostPort,
            'isolated_networks' => false,
            'status' => 'deploying',
            'last_error' => null,
        ]);

        $this->composeDeployStatus->sync($infrastructure);

        $this->attachMissingPublicDomains($infrastructure->refresh());

        return $infrastructure->refresh();
    }

    /**
     * Point MariaDB at a new named volume so MYSQL_* and MYSQL_ROOT_HOST apply on first init.
     * Does not delete the infrastructure or the customer data volume.
     */
    public function resetMysqlDatadir(Infrastructure $infrastructure): Infrastructure
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Only infrastructures created from this panel can reset MariaDB here.');
        }

        $generation = ((int) $infrastructure->mariadb_volume_generation) + 1;

        $infrastructure->update([
            'mariadb_volume_generation' => $generation,
            'mariadb_volume_name' => $infrastructure->slug.'_mariadb_v'.$generation,
        ]);

        $this->dokployCleanup(fn () => $this->dokploy->stopCompose([
            'composeId' => $composeId,
        ]));

        try {
            $infrastructure = $this->updateStack($infrastructure->refresh());
        } catch (Throwable $exception) {
            $this->failInfrastructure($infrastructure->refresh(), $exception);
        }

        $this->dokployCleanup(fn () => $this->dokploy->cleanUnusedVolumes());

        return $infrastructure->refresh();
    }

    public function attachPhpmyadminDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Only infrastructures created from this panel can receive a phpMyAdmin domain.');
        }

        $hostname = filled($host) ? strtolower((string) $host) : $this->composeTemplate->phpmyadminHostname($infrastructure->slug);

        $this->dokploy->createComposeDomain([
            'host' => $hostname,
            'port' => 80,
            'composeId' => $composeId,
            'serviceName' => 'phpmyadmin',
        ]);

        $infrastructure->update([
            'phpmyadmin_domain' => $hostname,
        ]);

        // Re-bake compose so PMA_ABSOLUTE_URI matches the public host (trailing slash for Traefik cookies).
        if ($redeploy) {
            return $this->updateStack($infrastructure->refresh());
        }

        return $infrastructure->refresh();
    }

    public function attachPgadminDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Only infrastructures created from this panel can receive a pgAdmin domain.');
        }

        $hostname = filled($host) ? strtolower((string) $host) : $this->composeTemplate->pgadminHostname($infrastructure->slug);

        $this->dokploy->createComposeDomain([
            'host' => $hostname,
            'port' => 80,
            'composeId' => $composeId,
            'serviceName' => 'pgadmin',
        ]);

        $infrastructure->update([
            'pgadmin_domain' => $hostname,
        ]);

        if ($redeploy) {
            $this->redeployCompose($infrastructure);
        }

        return $infrastructure->refresh();
    }

    public function attachMinioDomain(Infrastructure $infrastructure, ?string $host = null, bool $redeploy = true): Infrastructure
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            throw new RuntimeException('Only infrastructures created from this panel can receive a MinIO domain.');
        }

        $hostname = filled($host) ? strtolower((string) $host) : $this->composeTemplate->minioHostname($infrastructure->slug);

        $this->dokploy->createComposeDomain([
            'host' => $hostname,
            'port' => 9001,
            'composeId' => $composeId,
            'serviceName' => 'minio',
        ]);

        $infrastructure->update([
            'minio_domain' => $hostname,
        ]);

        if ($redeploy) {
            $this->redeployCompose($infrastructure);
        }

        return $infrastructure->refresh();
    }

    private function attachMissingPublicDomains(Infrastructure $infrastructure): void
    {
        $attempts = [
            ['phpmyadmin', 'phpmyadmin_domain', 'attachPhpmyadminDomain'],
            ['pgadmin', 'pgadmin_domain', 'attachPgadminDomain'],
            ['minio', 'minio_domain', 'attachMinioDomain'],
        ];

        $attached = false;

        foreach ($attempts as [$service, $column, $method]) {
            if (! $infrastructure->hasService($service) || filled($infrastructure->{$column})) {
                continue;
            }

            try {
                $this->{$method}($infrastructure, null, false);
                $attached = true;
                $infrastructure->refresh();
            } catch (Throwable $exception) {
                $this->failInfrastructure($infrastructure, $exception, keepStatus: true);
            }
        }

        if ($attached) {
            $this->redeployCompose($infrastructure);
        }
    }

    private function redeployCompose(Infrastructure $infrastructure): void
    {
        $composeId = $infrastructure->dokploy_compose_id;

        if (! is_string($composeId) || $composeId === '') {
            return;
        }

        $this->dokploy->deployCompose([
            'composeId' => $composeId,
        ]);

        $infrastructure->update([
            'status' => 'deploying',
            'last_error' => null,
        ]);

        $this->composeDeployStatus->sync($infrastructure);
    }

    /**
     * @param  array<string, mixed>  $createdProject
     */
    private function environmentIdForProject(array $createdProject, string $projectId): string
    {
        foreach ([
            data_get($createdProject, 'environmentId'),
            data_get($createdProject, 'environment.environmentId'),
            data_get($createdProject, 'environment.id'),
            data_get($createdProject, 'result.data.environment.environmentId'),
            data_get($createdProject, 'result.data.environmentId'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        $created = $this->dokploy->createEnvironment([
            'name' => 'production',
            'projectId' => $projectId,
        ]);

        return $this->dokploy->idFrom($created, 'environmentId');
    }

    private function pushCompose(string $composeId, string $composeFile, string $envFile): void
    {
        $this->dokploy->updateCompose([
            'composeId' => $composeId,
            'composeFile' => $composeFile,
            'env' => $envFile,
            'createEnvFile' => true,
            'isolatedDeployment' => false,
            'isolatedDeploymentsVolume' => false,
            'sourceType' => 'raw',
            'composeType' => 'docker-compose',
        ]);

        $this->dokploy->saveComposeEnvironment([
            'composeId' => $composeId,
            'env' => $envFile,
        ]);

        $this->dokploy->deployCompose([
            'composeId' => $composeId,
        ]);
    }

    /**
     * @return array{0: array{mysql_root_password: string, mysql_app_password: string, postgres_password: string, sftp_bootstrap_password: string, sftp_host_port: int}, 1: array<string, string>}
     */
    private function secretsFor(Infrastructure $infrastructure, int $sftpHostPort): array
    {
        $persist = [];

        $mysqlRoot = (string) ($infrastructure->mysql_root_password ?? '');
        if ($mysqlRoot === '') {
            $mysqlRoot = $this->generateSecret(32);
            $persist['mysql_root_password'] = $mysqlRoot;
        }

        $mysqlApp = (string) ($infrastructure->mysql_admin_password ?? '');
        if ($mysqlApp === '') {
            $mysqlApp = $this->generateSecret(24);
            $persist['mysql_admin_password'] = $mysqlApp;
        }

        $postgres = (string) ($infrastructure->postgres_admin_password ?? '');
        if ($postgres === '') {
            $postgres = $this->generateSecret(32);
            $persist['postgres_admin_password'] = $postgres;
        }

        $sftp = (string) ($infrastructure->sftp_bootstrap_password ?? '');
        if ($sftp === '' || str_contains($sftp, ':') || str_contains($sftp, ';')) {
            $sftp = $this->generateSecret(24);
            $persist['sftp_bootstrap_password'] = $sftp;
        }

        $sftpSyncToken = (string) ($infrastructure->sftp_sync_token ?? '');
        if ($sftpSyncToken === '') {
            $sftpSyncToken = $this->generateSecret(32);
            $persist['sftp_sync_token'] = $sftpSyncToken;
        }

        $pgadminEmail = (string) ($infrastructure->pgadmin_email ?? '');
        if ($pgadminEmail === '') {
            $pgadminEmail = $this->composeTemplate->pgadminEmail($infrastructure->slug);
            $persist['pgadmin_email'] = $pgadminEmail;
        }

        $pgadminPassword = (string) ($infrastructure->pgadmin_password ?? '');
        if ($pgadminPassword === '') {
            $pgadminPassword = $this->generateSecret(24);
            $persist['pgadmin_password'] = $pgadminPassword;
        }

        $minioUser = (string) ($infrastructure->minio_root_user ?? '');
        if ($minioUser === '') {
            $minioUser = $this->generateSecret(16);
            $persist['minio_root_user'] = $minioUser;
        }

        $minioPassword = (string) ($infrastructure->minio_root_password ?? '');
        if ($minioPassword === '') {
            $minioPassword = $this->generateSecret(32);
            $persist['minio_root_password'] = $minioPassword;
        }

        $persist['redis_host'] = $infrastructure->slug.'-redis';
        $persist['minio_host'] = $infrastructure->slug.'-minio';

        $mariadbVolumeName = (string) ($infrastructure->mariadb_volume_name ?? '');
        $phpmyadminDomain = (string) ($infrastructure->phpmyadmin_domain ?? '');

        return [[
            'mysql_root_password' => $mysqlRoot,
            'mysql_app_password' => $mysqlApp,
            'postgres_password' => $postgres,
            'sftp_bootstrap_password' => $sftp,
            'sftp_sync_token' => $sftpSyncToken,
            'sftp_host_port' => $sftpHostPort,
            'pgadmin_email' => $pgadminEmail,
            'pgadmin_password' => $pgadminPassword,
            'minio_root_user' => $minioUser,
            'minio_root_password' => $minioPassword,
            ...($mariadbVolumeName !== '' ? ['mariadb_volume_name' => $mariadbVolumeName] : []),
            ...($phpmyadminDomain !== ''
                ? ['phpmyadmin_absolute_uri' => $this->composeTemplate->phpmyadminAbsoluteUri(
                    $infrastructure->slug,
                    $phpmyadminDomain,
                )]
                : []),
        ], $persist];
    }

    private function generateSecret(int $length): string
    {
        $secret = Str::password($length, symbols: false);

        if (preg_match('/^[A-Za-z0-9]+$/', $secret) !== 1) {
            throw new RuntimeException('Generated compose secret is not usable.');
        }

        return $secret;
    }

    private function sftpHostPort(string $slug): int
    {
        $used = Infrastructure::query()
            ->where('slug', '!=', $slug)
            ->get()
            ->map(fn (Infrastructure $existing): int => $this->assignedSftpPort($existing))
            ->all();

        $port = $this->preferredSftpPort($slug);

        while (in_array($port, $used, true)) {
            $port++;
        }

        return $port;
    }

    private function assignedSftpPort(Infrastructure $infrastructure): int
    {
        if (filled($infrastructure->sftp_host_port)) {
            return (int) $infrastructure->sftp_host_port;
        }

        return $this->preferredSftpPort($infrastructure->slug);
    }

    private function preferredSftpPort(string $slug): int
    {
        if (preg_match('/(\d+)$/', $slug, $matches) === 1) {
            return 2221 + max((int) $matches[1], 1);
        }

        return 2222 + (abs(crc32($slug)) % 700);
    }

    /**
     * @return array{
     *     status: string,
     *     last_error: string|null,
     *     compose_status: string|null,
     *     app_name: string|null,
     *     last_deployment: array{status: string|null, title: string|null, created_at: string|null, error_message: string|null}|null,
     *     containers: list<array{id: string|null, name: string, service: string|null, state: string, status: string}>
     * }
     */
    public function refreshDeployStatus(Infrastructure $infrastructure): array
    {
        return $this->composeDeployStatus->sync($infrastructure);
    }

    private function markDeploying(Infrastructure $infrastructure): void
    {
        $infrastructure->update([
            'status' => 'deploying',
            'last_error' => null,
        ]);
    }

    private function failInfrastructure(Infrastructure $infrastructure, Throwable $exception, bool $keepStatus = false): never
    {
        $message = $this->dokploy->errorMessage($exception);

        $infrastructure->update([
            ...($keepStatus ? [] : ['status' => 'failed']),
            'last_error' => $message,
        ]);

        throw ValidationException::withMessages([
            'slug' => $message,
        ]);
    }
}
