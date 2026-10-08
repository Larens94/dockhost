<?php

// DokployApplicationAttacher.php — Creates Dokploy apps in the infra project/environment.
//
// exports: DokployApplicationAttacher | DokployApplicationAttacher::attach(Domain $domain, array $attributes = []): DokployApplication | DokployApplicationAttacher::alignBootEnv(Domain $domain): array | DokployApplicationAttacher::applyStackPreset(Domain $domain): array | DokployApplicationAttacher::applyLaravelDeployConfig(Domain $domain): array | DokployApplicationAttacher::refreshNixpacksInstallCommand(Domain $domain): void | DokployApplicationAttacher::syncPhpSettings(Domain $domain, bool $deployNow = false): array | DokployApplicationAttacher::nixpacksPreset(DomainStack $stack): array | DokployApplicationAttacher::laravelDeployPreset(): array
// used_by: app/Http/Controllers/DomainController.php
//         app/Services/Hosting/DomainProvisioner.php
//         app/Services/Hosting/DomainSiteEnvManager.php
// rules:   App joins the infra Dokploy environment (Isolated OFF → dokploy-network); DB_HOST is hostname (${slug}-mariadb), never an IP.
//          NEVER attach an extra {slug}-db network. Do NOT call InfraDataNetworks.
//          Env wiring uses DatabaseAccount (site user@'%' with GRANT on that one database only — not *.*).
//          NIXPACKS_START_CMD must use config:clear, not config:cache: Dokploy injects DB_* at runtime and config:cache can bake a stale DB password (1045 on sessions).
//          Laravel and PHP NIXPACKS_INSTALL_CMD must pass composer --no-scripts --no-interaction so package:discover does not boot the app against DB_HOST during the image build. Dokploy sends the same env to build and runtime, so do not unset DB_*. Panel deploy refreshes only that install command from the preset. Runtime NIXPACKS_START_CMD still runs migrate on dokploy-network.
//          APP_URL, ASSET_URL must be https://{fqdn} and TRUSTED_PROXIES=* behind Traefik or Inertia/Vite emit http:// URLs (mixed content).
// agent:   grok-4.7 | cursor | 2026-09-22 | s_20260922_composer_install | Install command runs composer before npm so artisan finds vendor
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_nginx_logdir | Start mkdir /var/log/nginx so nginx does not emerg on boot
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_session_cookie | Laravel session is database plus Secure cookie on the site host
//          grok-4.7 | cursor | 2026-09-23 | s_20260923_config_clear | Start uses config:clear so DB password matches Dokploy runtime env
//          grok-4.7 | cursor | 2026-09-23 | s_20260923_https_assets | ASSET_URL plus TRUSTED_PROXIES for Traefik TLS termination
//          composer-2.5-fast | cursor | 2026-09-23 | s_20260923_app_url | alignBootEnv replace APP_URL to https fqdn
//          composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | merge PHP_* env on attach, align, save
//          composer-2.5-fast | cursor | 2026-09-24 | s_php_ini_start | Prefix NIXPACKS_START_CMD to write dokhosts.ini from env.
//          grok-4.7 | cursor | 2026-10-08 | s_20261008_no_scripts | Install skips composer scripts so artisan does not resolve DB_HOST during image build
//          grok-4.7 | cursor | 2026-10-08 | s_20261008_deploy_install | Deploy refreshes composer NIXPACKS_INSTALL_CMD from the preset before the build
// message: Site GRANT stays one-database via MysqlProvisioner; admin infra user may keep *.*.

namespace App\Services\Dokploy;

use App\Enums\DomainStack;
use App\Models\DatabaseAccount;
use App\Models\DokployApplication;
use App\Models\Domain;
use App\Models\Infrastructure;
use App\Models\StorageShare;
use App\Support\DomainPhpSettings;
use App\Support\NixpacksStartCmdPhpIniPrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Validation\ValidationException;
use Throwable;

class DokployApplicationAttacher
{
    public function __construct(
        private DokployClient $dokploy,
    ) {}

    /**
     * Create the application in the infrastructure's Dokploy project/environment.
     *
     * Same Dokploy environment as the infra compose (Isolated OFF → dokploy-network) so
     * the app resolves `{slug}-mariadb` / `{slug}-postgres` by hostname. GitLab/Deploy
     * restano su Dokploy (link, non clone). Non chiamiamo saveGitProvider.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attach(Domain $domain, array $attributes = []): DokployApplication
    {
        $infrastructure = $domain->infrastructure
            ?? Infrastructure::query()->find($domain->infrastructure_id);

        if (! $infrastructure instanceof Infrastructure || ! filled($infrastructure->dokploy_environment_id)) {
            throw ValidationException::withMessages([
                'stack' => 'This stack has no Dokploy environment. Recreate it from the panel.',
            ]);
        }

        $stack = $domain->stack ?? DomainStack::None;

        if (! $stack->createsApplication()) {
            throw ValidationException::withMessages([
                'stack' => 'Questo dominio è solo hosting: scegli uno stack applicativo.',
            ]);
        }

        $environmentId = (string) $infrastructure->dokploy_environment_id;
        $account = $domain->databaseAccounts()->first();
        $storagePath = $this->storagePath($domain, $infrastructure);

        $payload = [
            'name' => $domain->fqdn,
            'appName' => $this->appName($domain->fqdn),
            'environmentId' => $environmentId,
        ];

        if (filled($infrastructure->dokploy_project_id)) {
            $payload['projectId'] = $infrastructure->dokploy_project_id;
        }

        $created = $this->dokploy->createApplication($payload);
        $applicationId = $this->dokploy->idFrom($created, 'applicationId');

        try {
            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $this->envFile($stack, $domain, $account, $storagePath, $infrastructure),
            ]);

            $this->dokploy->createDomain([
                'host' => $domain->fqdn,
                'path' => '/',
                'port' => (int) config('dokploy.app_port', 80),
                'https' => true,
                'certificateType' => 'letsencrypt',
                'applicationId' => $applicationId,
                'domainType' => 'application',
            ]);

            $this->dokploy->createMount([
                'type' => 'volume',
                'volumeName' => $infrastructure->slug.'_data',
                'mountPath' => '/data',
                'serviceType' => 'application',
                'serviceId' => $applicationId,
            ]);
            // Rules: no InfraDataNetworks::attachApplicationToDatabase — app reaches DB on dokploy-network by hostname.
        } catch (Throwable $exception) {
            try {
                $this->dokploy->deleteApplication([
                    'applicationId' => $applicationId,
                ]);
            } catch (Throwable) {
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'stack' => $this->dokploy->errorMessage($exception),
            ]);
        }

        return DokployApplication::query()->create([
            'domain_id' => $domain->id,
            'dokploy_application_id' => $applicationId,
            'dokploy_environment_id' => $environmentId,
            'git_url' => null,
        ]);
    }

    /**
     * Rules: Never overwrite APP_KEY or secrets. Do replace SESSION_* and APP_URL / ASSET_URL / TRUSTED_PROXIES: file sessions die on deploy (419), Traefik terminates TLS (Secure cookie, https URLs). SESSION_DOMAIN is the site fqdn. APP_URL and ASSET_URL must be https://{fqdn} or Inertia/Vite emit http:// URLs (mixed content).
     *
     * @return array{generated_app_key: bool, added: list<string>}
     */
    public function alignBootEnv(Domain $domain): array
    {
        $domain->loadMissing('dokployApplication');
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            throw ValidationException::withMessages([
                'align_env' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        if (! ($domain->stack ?? DomainStack::None)->isLaravel()) {
            throw ValidationException::withMessages([
                'align_env' => 'Allinea env di avvio è solo per stack Laravel.',
            ]);
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $merged = $this->mergeMissingBootEnv($currentEnv, $domain);
            $session = $this->replaceAssignments($merged['env'], $this->laravelSessionEnv($domain));
            $https = $this->replaceAssignments($session['env'], $this->laravelHttpsEnv($domain));
            $php = $this->replaceAssignments($https['env'], $this->phpSettingsEnvMap($domain));

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $php['env'],
            ]);

            return [
                'generated_app_key' => $merged['generated_app_key'],
                'added' => array_values(array_unique([
                    ...$merged['added'],
                    ...$session['updated'],
                    ...$session['added'],
                    ...$https['updated'],
                    ...$https['added'],
                    ...$php['updated'],
                    ...$php['added'],
                ])),
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'align_env' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * Apply Nixpacks/runtime env presets for the domain stack via application.saveEnvironment.
     * Merges keys without overwriting existing non-empty values (same idea as align boot env).
     *
     * @return array{added: list<string>, skipped: list<string>}
     */
    public function applyStackPreset(Domain $domain): array
    {
        $domain->loadMissing(['dokployApplication', 'infrastructure']);
        $applicationId = $domain->dokployApplication?->dokploy_application_id;
        $stack = $domain->stack ?? DomainStack::None;

        if (! is_string($applicationId) || $applicationId === '') {
            throw ValidationException::withMessages([
                'stack_preset' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        $preset = $this->nixpacksPreset($stack);

        if ($preset === []) {
            throw ValidationException::withMessages([
                'stack_preset' => 'Nessun preset Nixpacks per questo stack.',
            ]);
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $merged = $this->mergeMissingAssignments($currentEnv, $preset);

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);

            return [
                'added' => $merged['added'],
                'skipped' => $merged['skipped'],
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'stack_preset' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function nixpacksPreset(DomainStack $stack): array
    {
        return match ($stack) {
            DomainStack::Static => [
                'NIXPACKS_START_CMD' => 'npx --yes serve -s . -l ${PORT:-80}',
            ],
            DomainStack::Php => [
                'NIXPACKS_INSTALL_CMD' => 'composer install --no-dev --optimize-autoloader --no-interaction --no-scripts',
                'NIXPACKS_START_CMD' => 'php -S 0.0.0.0:${PORT:-80} -t public',
            ],
            DomainStack::Node => [
                'NODE_ENV' => 'production',
                'NIXPACKS_INSTALL_CMD' => 'npm ci',
                'NIXPACKS_BUILD_CMD' => 'npm run build',
                'NIXPACKS_START_CMD' => 'npm start',
            ],
            DomainStack::Python => [
                'NIXPACKS_INSTALL_CMD' => 'pip install -r requirements.txt',
                'NIXPACKS_START_CMD' => 'gunicorn -b 0.0.0.0:${PORT:-80} app:app',
            ],
            DomainStack::Go => [
                'NIXPACKS_BUILD_CMD' => 'go build -o /app/server .',
                'NIXPACKS_START_CMD' => '/app/server',
            ],
            DomainStack::Laravel, DomainStack::None => [],
        };
    }

    /**
     * Write Laravel Nixpacks build env and the container start command.
     *
     * Rules: saveEnvironment only — never docker exec. Replace NIXPACKS_INSTALL_CMD, NIXPACKS_BUILD_CMD and NIXPACKS_START_CMD in place. Leave every other env key untouched, including secrets. Laravel stack only.
     *
     * @return array{added: list<string>, updated: list<string>}
     */
    public function applyLaravelDeployConfig(Domain $domain): array
    {
        $domain->loadMissing(['dokployApplication']);
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            throw ValidationException::withMessages([
                'laravel_deploy' => 'Nessuna application Dokploy collegata a questo dominio.',
            ]);
        }

        if (! ($domain->stack ?? DomainStack::None)->isLaravel()) {
            throw ValidationException::withMessages([
                'laravel_deploy' => 'Build e avvio Nixpacks di questo tipo sono solo per stack Laravel.',
            ]);
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $preset = $this->laravelDeployPreset();
            $preset['NIXPACKS_START_CMD'] = NixpacksStartCmdPhpIniPrefix::apply($preset['NIXPACKS_START_CMD'])
                ?? $preset['NIXPACKS_START_CMD'];
            $merged = $this->replaceAssignments($currentEnv, $preset);

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);

            return [
                'added' => $merged['added'],
                'updated' => $merged['updated'],
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'laravel_deploy' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * Rewrite NIXPACKS_INSTALL_CMD from the current composer preset before a Nixpacks build.
     *
     * Dokploy passes the same application env into the image build and the container start.
     * composer scripts (package:discover) boot artisan during the build, and DB_HOST only
     * resolves on dokploy-network at runtime. --no-scripts skips that boot. DB_* stays set.
     * Stacks whose preset install command does not run composer are left untouched.
     * A matching install command is kept without a saveEnvironment call.
     */
    public function refreshNixpacksInstallCommand(Domain $domain): void
    {
        if ($this->nixpacksInstallCommand($domain->stack ?? DomainStack::None) === null) {
            return;
        }

        $domain->loadMissing('dokployApplication');
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            return;
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $merged = $this->mergeNixpacksInstallCommand($currentEnv, $domain);

            if ($merged['added'] === [] && $merged['updated'] === []) {
                return;
            }

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'deploy' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * Rules: replaceAssignments only — never drop unrelated env keys. Optional deploy after save.
     * When deploying, also replace NIXPACKS_INSTALL_CMD in the same save so the build skips composer scripts.
     *
     * @return array{env_synced: bool, added: list<string>, updated: list<string>, deployed: bool}
     */
    public function syncPhpSettings(Domain $domain, bool $deployNow = false): array
    {
        $domain->loadMissing('dokployApplication');
        $applicationId = $domain->dokployApplication?->dokploy_application_id;

        if (! is_string($applicationId) || $applicationId === '') {
            return [
                'env_synced' => false,
                'added' => [],
                'updated' => [],
                'deployed' => false,
            ];
        }

        try {
            $application = $this->dokploy->getApplication($applicationId);
            $currentEnv = is_string($application['env'] ?? null) ? $application['env'] : '';
            $merged = $this->replaceAssignments($currentEnv, $this->phpSettingsEnvMap($domain));
            $withIni = $this->ensurePhpIniStartCmdPrefix($merged['env'], $domain);
            $merged['env'] = $withIni['env'];
            $merged['updated'] = array_values(array_unique([...$merged['updated'], ...$withIni['updated']]));
            $merged['added'] = array_values(array_unique([...$merged['added'], ...$withIni['added']]));

            if ($deployNow) {
                $install = $this->mergeNixpacksInstallCommand($merged['env'], $domain);
                $merged['env'] = $install['env'];
                $merged['updated'] = array_values(array_unique([...$merged['updated'], ...$install['updated']]));
                $merged['added'] = array_values(array_unique([...$merged['added'], ...$install['added']]));
            }

            $this->dokploy->saveEnvironment([
                'applicationId' => $applicationId,
                'env' => $merged['env'],
            ]);

            $deployed = false;

            if ($deployNow) {
                $this->dokploy->deploy(['applicationId' => $applicationId]);
                $deployed = true;
            }

            return [
                'env_synced' => true,
                'added' => $merged['added'],
                'updated' => $merged['updated'],
                'deployed' => $deployed,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'php_settings' => $this->dokploy->errorMessage($exception),
            ]);
        }
    }

    /**
     * Nixpacks PHP provider starts nginx + php-fpm from /assets.
     *
     * Rules: NIXPACKS_INSTALL_CMD replaces the whole Nixpacks install phase, so it must mkdir /var/log/nginx and /var/cache/nginx, then composer install --ignore-platform-reqs --no-interaction --no-scripts, then npm ci. --no-scripts skips package:discover so the build does not boot the app against DB_HOST. npm ci alone drops vendor/autoload.php. Do not pass --no-dev. NIXPACKS_START_CMD must mkdir those nginx dirs again plus storage/framework/sessions (views, cache, logs, bootstrap/cache) and chmod a+rwx storage bootstrap/cache, because php-fpm runs as nobody and nginx emergs without /var/log/nginx/error.log. After migrate use config:clear, not config:cache, so DB_* from Dokploy runtime env is not frozen to a wrong password. End with the nginx + php-fpm start. Never artisan serve. Never a placeholder command.
     *
     * @return array<string, string>
     */
    public function laravelDeployPreset(): array
    {
        $prepare = 'mkdir -p /var/log/nginx /var/cache/nginx storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache && chmod -R a+rwx storage bootstrap/cache';
        $http = 'node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf && (php-fpm -y /assets/php-fpm.conf & nginx -c /nginx.conf)';

        return [
            'NIXPACKS_INSTALL_CMD' => 'mkdir -p /var/log/nginx /var/cache/nginx && composer install --ignore-platform-reqs --no-interaction --no-scripts && npm ci',
            'NIXPACKS_BUILD_CMD' => 'npm run build',
            'NIXPACKS_START_CMD' => $prepare.' && php artisan migrate --force && php artisan optimize:clear && php artisan config:clear && php artisan route:cache && php artisan view:cache && php artisan event:cache && php artisan queue:restart && '.$http,
        ];
    }

    private function appName(string $fqdn): string
    {
        $name = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $fqdn) ?? 'app');
        $name = trim($name, '-');

        return substr($name !== '' ? $name : 'app', 0, 63);
    }

    private function storagePath(Domain $domain, Infrastructure $infrastructure): string
    {
        $share = $domain->storageShares()->orderBy('id')->first();

        if ($share instanceof StorageShare && is_string($share->path) && $share->path !== '') {
            return $share->path;
        }

        $customerSlug = $domain->customer?->storageSlug() ?? 'shared';

        return rtrim($infrastructure->storage_root, '/').'/'.$customerSlug.'/'.$domain->fqdn;
    }

    /**
     * Runtime env: infra wiring (same Dokploy env → dokploy-network + DB_* from Infrastructure)
     * for every app stack; Laravel boot keys only when stack=laravel.
     * Composer stacks also get NIXPACKS_INSTALL_CMD with --no-scripts so the first build
     * does not boot artisan against DB_HOST.
     */
    private function envFile(
        DomainStack $stack,
        Domain $domain,
        ?DatabaseAccount $account,
        string $storagePath,
        Infrastructure $infrastructure,
    ): string {
        $lines = $this->infraWiringLines($domain, $account, $storagePath);

        if ($stack->usesLaravelEnv()) {
            $lines = [
                ...$this->laravelBootLines($domain),
                ...$lines,
                ...$this->laravelOptionalServiceLines($infrastructure, $domain),
            ];
        }

        $lines = [...$lines, ...$this->phpSettingsEnvLines($domain)];

        $installCommand = $this->nixpacksInstallCommand($stack);

        if ($installCommand !== null) {
            $lines[] = 'NIXPACKS_INSTALL_CMD='.$installCommand;
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Install command that must not boot the app during the Nixpacks build.
     * Null for stacks whose preset does not run composer.
     */
    private function nixpacksInstallCommand(DomainStack $stack): ?string
    {
        if ($stack->isLaravel()) {
            return $this->laravelDeployPreset()['NIXPACKS_INSTALL_CMD'];
        }

        if ($stack !== DomainStack::Php) {
            return null;
        }

        return $this->nixpacksPreset($stack)['NIXPACKS_INSTALL_CMD'] ?? null;
    }

    /**
     * @return array{env: string, added: list<string>, updated: list<string>}
     */
    private function mergeNixpacksInstallCommand(string $env, Domain $domain): array
    {
        $command = $this->nixpacksInstallCommand($domain->stack ?? DomainStack::None);

        if ($command === null) {
            return [
                'env' => $env,
                'added' => [],
                'updated' => [],
            ];
        }

        return $this->replaceAssignments($env, [
            'NIXPACKS_INSTALL_CMD' => $command,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function phpSettingsEnvMap(Domain $domain): array
    {
        return DomainPhpSettings::toDokployEnv(DomainPhpSettings::resolved($domain->php_settings));
    }

    /**
     * @return list<string>
     */
    private function phpSettingsEnvLines(Domain $domain): array
    {
        $lines = [];

        foreach ($this->phpSettingsEnvMap($domain) as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        return $lines;
    }

    /**
     * Shared for all app stacks: APP_URL, storage path, DB_* from the chosen infra when a DB exists.
     *
     * @return list<string>
     */
    private function infraWiringLines(Domain $domain, ?DatabaseAccount $account, string $storagePath): array
    {
        $lines = [
            'APP_URL=https://'.$domain->fqdn,
            'DOKHOSTS_STORAGE_PATH='.$storagePath,
            'DOKHOSTS_INFRA_SLUG='.$domain->infra_slug,
        ];

        if ($account !== null) {
            $lines[] = 'DB_CONNECTION='.($account->engine->value === 'postgres' ? 'pgsql' : 'mysql');
            $lines[] = 'DB_HOST='.$account->host;
            $lines[] = 'DB_PORT='.(string) $account->port;
            $lines[] = 'DB_DATABASE='.$account->database_name;
            $lines[] = 'DB_USERNAME='.$account->username;
            $lines[] = 'DB_PASSWORD='.$account->password_encrypted;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function laravelBootLines(Domain $domain): array
    {
        return [
            'APP_NAME='.$domain->fqdn,
            'APP_ENV=production',
            'APP_KEY='.$this->generateApplicationKey(),
            'APP_DEBUG=false',
            'LOG_CHANNEL=stderr',
            'FILESYSTEM_DISK=local',
            ...$this->laravelSessionLines($domain),
            ...$this->laravelHttpsLines($domain),
            'CACHE_STORE=file',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function laravelHttpsEnv(Domain $domain): array
    {
        $httpsUrl = 'https://'.$domain->fqdn;

        return [
            'APP_URL' => $httpsUrl,
            'ASSET_URL' => $httpsUrl,
            'TRUSTED_PROXIES' => '*',
        ];
    }

    /**
     * @return list<string>
     */
    private function laravelHttpsLines(Domain $domain): array
    {
        $httpsUrl = 'https://'.$domain->fqdn;

        return [
            'APP_URL='.$httpsUrl,
            'ASSET_URL='.$httpsUrl,
            'TRUSTED_PROXIES=*',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function laravelSessionEnv(Domain $domain): array
    {
        return [
            'SESSION_DRIVER' => 'database',
            'SESSION_SECURE_COOKIE' => 'true',
            'SESSION_SAME_SITE' => 'lax',
            'SESSION_DOMAIN' => $domain->fqdn,
            'SESSION_COOKIE' => str_replace(['.', '-'], '_', $domain->fqdn).'_session',
        ];
    }

    /**
     * @return list<string>
     */
    private function laravelSessionLines(Domain $domain): array
    {
        $lines = [];

        foreach ($this->laravelSessionEnv($domain) as $key => $value) {
            $lines[] = $key.'='.$value;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function laravelOptionalServiceLines(Infrastructure $infrastructure, Domain $domain): array
    {
        $lines = [];

        if ($infrastructure->hasService('redis')) {
            $lines[] = 'REDIS_HOST='.($infrastructure->redis_host ?: $infrastructure->slug.'-redis');
            $lines[] = 'REDIS_PORT=6379';
        }

        if ($infrastructure->hasService('minio') && filled($infrastructure->minio_root_user) && filled($infrastructure->minio_root_password)) {
            $bucket = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $domain->fqdn) ?? 'app');
            $bucket = trim($bucket, '-');

            $lines[] = 'AWS_ACCESS_KEY_ID='.$infrastructure->minio_root_user;
            $lines[] = 'AWS_SECRET_ACCESS_KEY='.$infrastructure->minio_root_password;
            $lines[] = 'AWS_DEFAULT_REGION=us-east-1';
            $lines[] = 'AWS_BUCKET='.($bucket !== '' ? $bucket : 'app');
            $lines[] = 'AWS_ENDPOINT=http://'.($infrastructure->minio_host ?: $infrastructure->slug.'-minio').':9000';
            $lines[] = 'AWS_USE_PATH_STYLE_ENDPOINT=true';
        }

        return $lines;
    }

    /**
     * Same format as `php artisan key:generate`: base64: + 32 random bytes.
     */
    private function generateApplicationKey(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC'));
    }

    /**
     * @return array{env: string, generated_app_key: bool, added: list<string>}
     */
    private function mergeMissingBootEnv(string $currentEnv, Domain $domain): array
    {
        $existing = $this->envAssignments($currentEnv);
        $additions = [];
        $generatedAppKey = false;

        foreach ($this->bootEnvDefaults($domain) as $key => $value) {
            if ($this->envValueIsPresent($existing[$key] ?? null)) {
                continue;
            }

            if ($key === 'APP_KEY') {
                $value = $this->generateApplicationKey();
                $generatedAppKey = true;
            }

            $additions[] = $key.'='.$value;
        }

        $env = rtrim(str_replace(["\r\n", "\r"], "\n", $currentEnv), "\n");

        if ($additions === []) {
            return [
                'env' => $env === '' ? '' : $env."\n",
                'generated_app_key' => false,
                'added' => [],
            ];
        }

        $merged = ($env === '' ? '' : $env."\n").implode("\n", $additions)."\n";

        return [
            'env' => $merged,
            'generated_app_key' => $generatedAppKey,
            'added' => array_map(
                fn (string $line): string => strstr($line, '=', true) ?: $line,
                $additions,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $additions
     * @return array{env: string, added: list<string>, skipped: list<string>}
     */
    private function mergeMissingAssignments(string $currentEnv, array $additions): array
    {
        $existing = $this->envAssignments($currentEnv);
        $toAppend = [];
        $added = [];
        $skipped = [];

        foreach ($additions as $key => $value) {
            if ($this->envValueIsPresent($existing[$key] ?? null)) {
                $skipped[] = $key;

                continue;
            }

            $toAppend[] = $key.'='.$value;
            $added[] = $key;
        }

        $env = rtrim(str_replace(["\r\n", "\r"], "\n", $currentEnv), "\n");

        if ($toAppend === []) {
            return [
                'env' => $env === '' ? '' : $env."\n",
                'added' => [],
                'skipped' => $skipped,
            ];
        }

        $merged = ($env === '' ? '' : $env."\n").implode("\n", $toAppend)."\n";

        return [
            'env' => $merged,
            'added' => $added,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<string, string>
     */
    /**
     * Rules: only Laravel/PHP stacks with non-empty NIXPACKS_START_CMD — idempotent prefix.
     *
     * @return array{env: string, added: list<string>, updated: list<string>}
     */
    private function ensurePhpIniStartCmdPrefix(string $env, Domain $domain): array
    {
        $stack = $domain->stack ?? DomainStack::None;

        if (! $stack->usesPhpIniAtRuntime()) {
            return [
                'env' => $env,
                'added' => [],
                'updated' => [],
            ];
        }

        $assignments = $this->envAssignments($env);
        $start = $assignments['NIXPACKS_START_CMD'] ?? '';

        if (trim($start) === '') {
            return [
                'env' => $env,
                'added' => [],
                'updated' => [],
            ];
        }

        $prefixed = NixpacksStartCmdPhpIniPrefix::apply($start);

        if ($prefixed === $start) {
            return [
                'env' => $env,
                'added' => [],
                'updated' => [],
            ];
        }

        return $this->replaceAssignments($env, [
            'NIXPACKS_START_CMD' => (string) $prefixed,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function envAssignments(string $env): array
    {
        $assignments = [];

        foreach (preg_split("/\r\n|\n|\r/", $env) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (! str_contains($trimmed, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key !== '') {
                $assignments[$key] = $value;
            }
        }

        return $assignments;
    }

    /**
     * @param  array<string, string>  $replacements
     * @return array{env: string, added: list<string>, updated: list<string>}
     */
    private function replaceAssignments(string $currentEnv, array $replacements): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $currentEnv) ?: [];
        $rewritten = [];
        $seen = [];
        $added = [];
        $updated = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                $rewritten[] = $line;

                continue;
            }

            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim($key);

            if ($key === '' || ! array_key_exists($key, $replacements)) {
                $rewritten[] = $line;

                continue;
            }

            $seen[$key] = true;

            if ($value === $replacements[$key]) {
                $rewritten[] = $key.'='.$value;

                continue;
            }

            $updated[] = $key;
            $rewritten[] = $key.'='.$replacements[$key];
        }

        foreach ($replacements as $key => $value) {
            if (isset($seen[$key])) {
                continue;
            }

            $added[] = $key;
            $rewritten[] = $key.'='.$value;
        }

        $env = rtrim(implode("\n", $rewritten), "\n");

        return [
            'env' => $env === '' ? '' : $env."\n",
            'added' => $added,
            'updated' => $updated,
        ];
    }

    private function envValueIsPresent(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = trim($value);

        if (
            (str_starts_with($normalized, '"') && str_ends_with($normalized, '"'))
            || (str_starts_with($normalized, "'") && str_ends_with($normalized, "'"))
        ) {
            $normalized = substr($normalized, 1, -1);
        }

        return trim($normalized) !== '';
    }

    /**
     * @return array<string, string>
     */
    private function bootEnvDefaults(Domain $domain): array
    {
        return [
            'APP_NAME' => $domain->fqdn,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => '',
            ...$this->laravelSessionEnv($domain),
            'CACHE_STORE' => 'file',
            'LOG_CHANNEL' => 'stderr',
        ];
    }
}
