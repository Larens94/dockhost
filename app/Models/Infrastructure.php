<?php

// Infrastructure.php — Infrastructure module.
//
// exports: Infrastructure | attr:Fillable | attr:Hidden | Infrastructure::nextSlug(): string | Infrastructure::scopePanelManaged(Builder $query): Builder | Infrastructure::domains(): HasMany | Infrastructure::databaseAccounts(): HasMany | Infrastructure::sftpUsers(): HasMany | Infrastructure::isPanelManaged(): bool | Infrastructure::isDeployed(): bool | Infrastructure::hasService(string $key): bool | Infrastructure::canHostDomains(): bool | Infrastructure::canProvisionMysql(): bool | Infrastructure::canProvisionPostgres(): bool | Infrastructure::stackCredentials(): array | Infrastructure::assignmentPayload(): array | Infrastructure::dokployProjectUrl() | Infrastructure::dokployComposeUrl() | Infrastructure::dokployApplicationUrl(?string $applicationId) | Infrastructure::sftpSyncUrl(): string | (+2 more)
// used_by: app/Console/Commands/AlignMysqlFromComposeCommand.php
//         app/Console/Commands/DokployInspectCommand.php
//         app/Http/Controllers/DomainController.php
//         app/Http/Controllers/InfrastructureController.php
//         app/Http/Requests/AttachMinioDomainRequest.php
//         app/Http/Requests/AttachPgadminDomainRequest.php
//         app/Http/Requests/AttachPhpmyadminDomainRequest.php
//         app/Http/Requests/DestroyInfrastructureRequest.php
//         app/Http/Requests/RecreateMysqlDatadirRequest.php
//         app/Http/Requests/StoreDomainDatabaseRequest.php
//         app/Http/Requests/StoreDomainRequest.php
//         app/Http/Requests/StoreInfrastructureDatabaseUserRequest.php
//         app/Http/Requests/StoreInfrastructureSftpUserRequest.php
//         app/Services/Dokploy/DokployApplicationAttacher.php
//         app/Services/Hosting/AccessAccountManager.php
//         app/Services/Hosting/DomainProvisioner.php
//         app/Services/Infra/ComposeDeployStatus.php
//         app/Services/Infra/ComposeMysqlCredentialAligner.php
//         app/Services/Infra/ComposeRuntimeInspector.php
//         app/Services/Infra/InfrastructureProvisioner.php
//         app/Services/Infra/MysqlProvisioner.php
//         app/Services/Infra/PostgresProvisioner.php
//         app/Services/Infra/SftpDaemonSync.php
//         database/factories/DomainFactory.php
//         database/factories/InfrastructureFactory.php
//         tests/Feature/AttachOptionalStackDomainsTest.php
//         tests/Feature/AttachPhpmyadminDomainTest.php
//         tests/Feature/DestroyInfrastructureTest.php
//         tests/Feature/DokployInspectTest.php
//         tests/Feature/InfrastructureDeployStatusTest.php
//         tests/Feature/InfrastructureProvisionTest.php
//         tests/Feature/InfrastructureStackUpdateTest.php
//         tests/TestCase.php
//         tests/Unit/ComposeRuntimeInspectorTest.php
//         tests/Unit/DokployDashboardUrlTest.php
// rules:   none
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Models;

use App\Services\Dokploy\DokployDashboardUrl;
use App\Services\Infra\ComposeTemplate;
use Database\Factories\InfrastructureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'slug',
    'name',
    'template',
    'enabled_services',
    'dokploy_project_id',
    'dokploy_environment_id',
    'dokploy_compose_id',
    'status',
    'mysql_host',
    'mysql_port',
    'mysql_admin_user',
    'mysql_admin_password',
    'mysql_root_password',
    'postgres_host',
    'postgres_port',
    'postgres_admin_user',
    'postgres_admin_password',
    'postgres_admin_database',
    'sftp_host',
    'sftp_host_port',
    'sftp_bootstrap_password',
    'phpmyadmin_domain',
    'pgadmin_domain',
    'minio_domain',
    'pgadmin_email',
    'pgadmin_password',
    'minio_root_user',
    'minio_root_password',
    'redis_host',
    'minio_host',
    'storage_root',
    'sftp_users_file',
    'sftp_sync_token',
    'mariadb_volume_generation',
    'mariadb_volume_name',
    'panel_volumes_attached',
    'isolated_networks',
    'last_error',
    'panel_volumes_error',
])]
#[Hidden(['mysql_admin_password', 'mysql_root_password', 'postgres_admin_password', 'sftp_bootstrap_password', 'sftp_sync_token', 'pgadmin_password', 'minio_root_password'])]
class Infrastructure extends Model
{
    /** @use HasFactory<InfrastructureFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = [
        'sftp_public_host',
        'dokploy_project_url',
        'dokploy_compose_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mysql_port' => 'integer',
            'postgres_port' => 'integer',
            'sftp_host_port' => 'integer',
            'mariadb_volume_generation' => 'integer',
            'mysql_admin_password' => 'encrypted',
            'mysql_root_password' => 'encrypted',
            'postgres_admin_password' => 'encrypted',
            'sftp_bootstrap_password' => 'encrypted',
            'sftp_sync_token' => 'encrypted',
            'pgadmin_password' => 'encrypted',
            'minio_root_password' => 'encrypted',
            'panel_volumes_attached' => 'boolean',
            'isolated_networks' => 'boolean',
            'enabled_services' => 'array',
        ];
    }

    public static function nextSlug(): string
    {
        $used = static::query()->pluck('slug');
        $number = 1;

        while ($used->contains('infra'.$number)) {
            $number++;
        }

        return 'infra'.$number;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePanelManaged(Builder $query): Builder
    {
        return $query->whereNotNull('dokploy_compose_id')->where('dokploy_compose_id', '!=', '');
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function databaseAccounts(): HasMany
    {
        return $this->hasMany(DatabaseAccount::class);
    }

    public function sftpUsers(): HasMany
    {
        return $this->hasMany(SftpUser::class);
    }

    public function isPanelManaged(): bool
    {
        return is_string($this->dokploy_compose_id) && $this->dokploy_compose_id !== '';
    }

    public function isDeployed(): bool
    {
        return in_array($this->status, ['deployed', 'ready'], true);
    }

    public function hasService(string $key): bool
    {
        $enabled = $this->enabled_services ?? app(ComposeTemplate::class)->defaultEnabledServices();

        return in_array($key, $enabled, true);
    }

    public function canHostDomains(): bool
    {
        return $this->isPanelManaged() && $this->hasService('sftp');
    }

    public function canProvisionMysql(): bool
    {
        return $this->isPanelManaged() && $this->isDeployed() && $this->hasService('mariadb');
    }

    public function canProvisionPostgres(): bool
    {
        return $this->isPanelManaged() && $this->isDeployed() && $this->hasService('postgres');
    }

    /**
     * Plaintext for the panel while we develop. Later these go out by email, not in the UI.
     *
     * @return array<string, mixed>
     */
    public function stackCredentials(): array
    {
        return [
            'mysql' => [
                'label' => 'phpMyAdmin / MariaDB app',
                'username' => $this->mysql_admin_user,
                'password' => $this->mysql_admin_password,
                'host' => $this->mysql_host,
                'port' => $this->mysql_port,
            ],
            'mysql_root' => [
                'label' => 'MariaDB root',
                'username' => 'root',
                'password' => $this->mysql_root_password,
                'host' => $this->mysql_host,
                'port' => $this->mysql_port,
            ],
            'postgres' => [
                'label' => 'pgAdmin / Postgres',
                'username' => $this->postgres_admin_user,
                'password' => $this->postgres_admin_password,
                'host' => $this->postgres_host,
                'port' => $this->postgres_port,
            ],
            'sftp' => [
                'label' => 'SFTP bootstrap',
                'username' => 'infra',
                'password' => $this->sftp_bootstrap_password,
                'host' => $this->publicSftpHost(),
                'port' => $this->sftp_host_port,
            ],
            'pgadmin' => filled($this->pgadmin_email) ? [
                'label' => 'pgAdmin login',
                'username' => $this->pgadmin_email,
                'password' => $this->pgadmin_password,
                'host' => $this->pgadmin_domain,
            ] : null,
            'minio' => filled($this->minio_root_user) ? [
                'label' => 'MinIO console',
                'username' => $this->minio_root_user,
                'password' => $this->minio_root_password,
                'host' => $this->minio_domain,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentPayload(): array
    {
        return [
            ...$this->domainMemberPayload(),
            'dokploy_project_url' => $this->dokployProjectUrl(),
            'dokploy_compose_url' => $this->dokployComposeUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function domainMemberPayload(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'status' => $this->status,
            'can_host_domains' => $this->canHostDomains(),
            'can_mysql' => $this->canProvisionMysql(),
            'can_postgres' => $this->canProvisionPostgres(),
            'mysql_host' => $this->mysql_host,
            'postgres_host' => $this->postgres_host,
            'sftp_host' => $this->sftp_host,
            'sftp_host_port' => $this->sftp_host_port,
            'sftp_public_host' => $this->publicSftpHost(),
        ];
    }

    public function dokployProjectUrl(): ?string
    {
        return app(DokployDashboardUrl::class)->projectEnvironment(
            $this->dokploy_project_id,
            $this->dokploy_environment_id,
        );
    }

    public function dokployComposeUrl(): ?string
    {
        return app(DokployDashboardUrl::class)->compose(
            $this->dokploy_project_id,
            $this->dokploy_environment_id,
            $this->dokploy_compose_id,
        );
    }

    public function dokployApplicationUrl(?string $applicationId): ?string
    {
        return app(DokployDashboardUrl::class)->application(
            $this->dokploy_project_id,
            $this->dokploy_environment_id,
            $applicationId,
        );
    }

    public function sftpSyncUrl(): string
    {
        $template = (string) config('infra.sftp.sync_url', 'http://{slug}-sftp-sync:8787/sync');

        return str_replace('{slug}', $this->slug, $template);
    }

    public function publicSftpHost(): string
    {
        $host = (string) config('dokploy.public_host');
        $host = preg_replace('#^https?://#', '', $host) ?? $host;

        return trim($host, '/.');
    }

    /**
     * @return Attribute<string, never>
     */
    protected function sftpPublicHost(): Attribute
    {
        return Attribute::get(fn (): string => $this->publicSftpHost());
    }

    protected function getDokployProjectUrlAttribute(): ?string
    {
        return $this->dokployProjectUrl();
    }

    protected function getDokployComposeUrlAttribute(): ?string
    {
        return $this->dokployComposeUrl();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function assignablePayloads(): Collection
    {
        return static::query()
            ->panelManaged()
            ->orderBy('slug')
            ->orderBy('id')
            ->get()
            ->map(fn (self $infrastructure): array => $infrastructure->assignmentPayload())
            ->values();
    }
}
