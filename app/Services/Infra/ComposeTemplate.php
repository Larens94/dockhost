<?php

// ComposeTemplate.php — Renders infra Docker Compose YAML + env for Dokploy.
//
// exports: ComposeTemplate | ComposeTemplate::catalog(): array | ComposeTemplate::pickerCatalog(): array | ComposeTemplate::allowedServiceKeys(): array | ComposeTemplate::requiredServiceKeys(): array | ComposeTemplate::defaultEnabledServices(): array | ComposeTemplate::normalizeEnabled(?array $enabled): array | ComposeTemplate::render(string $slug, int $sftpHostPort, ?array $enabledServices = null, array $secrets = [], bool $isolatedNetworks = false): string | ComposeTemplate::interpolateStack(string $yaml, string $slug, int $sftpHostPort, array $secrets = []): string | ComposeTemplate::envFile(string $slug, array $secrets): string | ComposeTemplate::phpmyadminHostname(string $slug): string | ComposeTemplate::phpmyadminAbsoluteUri(string $slug, ?string $host = null): string | ComposeTemplate::pgadminHostname(string $slug): string | ComposeTemplate::minioHostname(string $slug): string | ComposeTemplate::pgadminEmail(string $slug): string | ComposeTemplate::publicHost(): string
// used_by: app/Http/Controllers/InfrastructureController.php
//         app/Http/Requests/StoreInfrastructureRequest.php
//         app/Http/Requests/UpdateInfrastructureRequest.php
//         app/Models/Infrastructure.php
//         app/Services/Infra/InfrastructureProvisioner.php
//         tests/Unit/ComposeTemplateTest.php
// rules:   Default/create path uses isolatedNetworks=false → every service on dokploy-network with aliases ${slug}-*.
//          NEVER invent {slug}-db/{slug}-storage for provisioners; isolatedNetworks=true is UNUSED legacy mode only.
//          Bake slug/ports/secrets into YAML — no ${INFRA_SLUG} left for Dokploy. Dokploy Isolated Deployments stay OFF.
//          mysql-grants passes the root password as -p"$$MYSQL_PWD". The MariaDB 11 client ignores MYSQL_PWD, so a passwordless login gets 1045 even when the env var is set. Escape every shell $ as $$ or Compose empties it. Never embed those passwords in the shell string and never ALTER root.
//          PMA_ABSOLUTE_URI must be https://host/ with a trailing slash — without it phpMyAdmin behind Traefik drops the session cookie and the login form loops.
// agent:   composer | cursor | 2026-09-21 | s_20260921_shared_net | Document shared-network as the shipped default
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_mysql_grants | Grants only the infra user from env, so a second start still logs in
//          grok-4.7 | cursor | 2026-09-22 | s_20260922_grants_pflag | Pass root password with -p; MariaDB 11 ignores MYSQL_PWD
//          composer-2.5-fast | cursor | 2026-10-10 | s_pma_uri_slash | Trailing slash + domain override for Traefik cookie login
// message: render(..., true) kept for unit docs of unused isolated mode — callers must pass false.

namespace App\Services\Infra;

class ComposeTemplate
{
    /**
     * @return list<array{key: string, label: string, required: bool, default_enabled: bool, picker: bool, role: string, hostname_suffix: string|null, summary: string}>
     */
    public function catalog(): array
    {
        return [
            $this->service('mariadb', true, true, true, 'database', 'mariadb'),
            $this->service('mysql-grants', true, true, false, 'helper', null),
            $this->service('postgres', true, true, true, 'database', 'postgres'),
            $this->service('sftp-users-init', true, true, false, 'helper', null),
            $this->service('sftp-sync', true, true, false, 'helper', 'sftp-sync'),
            $this->service('sftp', true, true, true, 'storage', 'sftp'),
            $this->service('phpmyadmin', false, true, true, 'ui', 'phpmyadmin'),
            $this->service('pgadmin', false, false, true, 'ui', 'pgadmin'),
            $this->service('redis', false, false, true, 'cache', 'redis'),
            $this->service('minio', false, false, true, 'storage', 'minio'),
        ];
    }

    /**
     * @return array{key: string, label: string, required: bool, default_enabled: bool, picker: bool, role: string, hostname_suffix: string|null, summary: string}
     */
    private function service(string $key, bool $required, bool $defaultEnabled, bool $picker, string $role, ?string $hostnameSuffix): array
    {
        return [
            'key' => $key,
            'label' => __('panel.services.'.$key.'.label'),
            'required' => $required,
            'default_enabled' => $defaultEnabled,
            'picker' => $picker,
            'role' => $role,
            'hostname_suffix' => $hostnameSuffix,
            'summary' => __('panel.services.'.$key.'.summary'),
        ];
    }

    /**
     * @return list<array{key: string, label: string, required: bool, default_enabled: bool, picker: bool, role: string, hostname_suffix: string|null, summary: string}>
     */
    public function pickerCatalog(): array
    {
        return array_values(array_filter($this->catalog(), fn (array $service): bool => $service['picker']));
    }

    /**
     * @return list<string>
     */
    public function allowedServiceKeys(): array
    {
        return array_column($this->catalog(), 'key');
    }

    /**
     * @return list<string>
     */
    public function requiredServiceKeys(): array
    {
        return array_values(array_map(
            fn (array $service): string => $service['key'],
            array_filter($this->catalog(), fn (array $service): bool => $service['required']),
        ));
    }

    /**
     * @return list<string>
     */
    public function defaultEnabledServices(): array
    {
        return array_values(array_map(
            fn (array $service): string => $service['key'],
            array_filter(
                $this->catalog(),
                fn (array $service): bool => $service['required'] || $service['default_enabled'],
            ),
        ));
    }

    /**
     * @param  list<string>|null  $enabled
     * @return list<string>
     */
    public function normalizeEnabled(?array $enabled): array
    {
        $allowed = $this->allowedServiceKeys();
        $required = $this->requiredServiceKeys();
        $chosen = $enabled === null
            ? $this->defaultEnabledServices()
            : array_values(array_intersect($allowed, $enabled));
        $merged = array_unique([...$required, ...$chosen]);

        return array_values(array_filter(
            $allowed,
            fn (string $key): bool => in_array($key, $merged, true),
        ));
    }

    /**
     * Bake slug, SFTP port, and secrets into YAML so Dokploy does not have to interpolate them.
     *
     * @param  list<string>|null  $enabledServices
     * @param  array{mysql_root_password?: string, mysql_app_password?: string, postgres_password?: string, sftp_bootstrap_password?: string, sftp_host_port?: int, pgadmin_email?: string, pgadmin_password?: string, minio_root_user?: string, minio_root_password?: string, mariadb_volume_name?: string}  $secrets
     */
    public function render(string $slug, int $sftpHostPort, ?array $enabledServices = null, array $secrets = [], bool $isolatedNetworks = false): string
    {
        $yaml = $this->interpolateStack($this->applyNetworkMode($this->baseYaml(), $isolatedNetworks), $slug, $sftpHostPort, $secrets);
        $enabled = $this->normalizeEnabled($enabledServices);

        foreach ($this->catalog() as $service) {
            if ($service['required'] || in_array($service['key'], $enabled, true)) {
                continue;
            }

            $yaml = $this->removeService($yaml, $service['key']);
        }

        return $this->pruneUnusedVolumes($yaml);
    }

    /**
     * @param  array{mysql_root_password?: string, mysql_app_password?: string, postgres_password?: string, sftp_bootstrap_password?: string, sftp_host_port?: int, pgadmin_email?: string, pgadmin_password?: string, minio_root_user?: string, minio_root_password?: string, mariadb_volume_name?: string, phpmyadmin_absolute_uri?: string}  $secrets
     */
    public function interpolateStack(string $yaml, string $slug, int $sftpHostPort, array $secrets = []): string
    {
        $phpmyadminAbsoluteUri = filled($secrets['phpmyadmin_absolute_uri'] ?? null)
            ? $this->normalizeAbsoluteUri((string) $secrets['phpmyadmin_absolute_uri'])
            : $this->phpmyadminAbsoluteUri($slug);

        $replacements = [
            '${INFRA_SLUG:-infra1}' => $slug,
            '${INFRA_SLUG}' => $slug,
            '${SFTP_HOST_PORT:-2222}' => (string) $sftpHostPort,
            '${SFTP_HOST_PORT}' => (string) $sftpHostPort,
            '${PMA_ABSOLUTE_URI}' => $phpmyadminAbsoluteUri,
        ];

        if (filled($secrets['mysql_root_password'] ?? null)) {
            $replacements['${MYSQL_ROOT_PASSWORD:-changeme}'] = (string) $secrets['mysql_root_password'];
            $replacements['${MYSQL_ROOT_PASSWORD}'] = (string) $secrets['mysql_root_password'];
        }

        if (filled($secrets['mysql_app_password'] ?? null)) {
            $replacements['${MYSQL_APP_PASSWORD:-changeme}'] = (string) $secrets['mysql_app_password'];
            $replacements['${MYSQL_APP_PASSWORD}'] = (string) $secrets['mysql_app_password'];
        }

        if (filled($secrets['postgres_password'] ?? null)) {
            $replacements['${POSTGRES_PASSWORD:-changeme}'] = (string) $secrets['postgres_password'];
            $replacements['${POSTGRES_PASSWORD}'] = (string) $secrets['postgres_password'];
        }

        if (filled($secrets['sftp_bootstrap_password'] ?? null)) {
            $replacements['${SFTP_BOOTSTRAP_PASSWORD:-changeme}'] = (string) $secrets['sftp_bootstrap_password'];
            $replacements['${SFTP_BOOTSTRAP_PASSWORD}'] = (string) $secrets['sftp_bootstrap_password'];
        }

        if (filled($secrets['sftp_sync_token'] ?? null)) {
            $replacements['${SFTP_SYNC_TOKEN:-changeme}'] = (string) $secrets['sftp_sync_token'];
            $replacements['${SFTP_SYNC_TOKEN}'] = (string) $secrets['sftp_sync_token'];
        }

        if (filled($secrets['pgadmin_email'] ?? null)) {
            $replacements['${PGADMIN_DEFAULT_EMAIL:-admin@local}'] = (string) $secrets['pgadmin_email'];
            $replacements['${PGADMIN_DEFAULT_EMAIL}'] = (string) $secrets['pgadmin_email'];
        }

        if (filled($secrets['pgadmin_password'] ?? null)) {
            $replacements['${PGADMIN_DEFAULT_PASSWORD:-changeme}'] = (string) $secrets['pgadmin_password'];
            $replacements['${PGADMIN_DEFAULT_PASSWORD}'] = (string) $secrets['pgadmin_password'];
        }

        if (filled($secrets['minio_root_user'] ?? null)) {
            $replacements['${MINIO_ROOT_USER:-minio}'] = (string) $secrets['minio_root_user'];
            $replacements['${MINIO_ROOT_USER}'] = (string) $secrets['minio_root_user'];
        }

        if (filled($secrets['minio_root_password'] ?? null)) {
            $replacements['${MINIO_ROOT_PASSWORD:-changeme}'] = (string) $secrets['minio_root_password'];
            $replacements['${MINIO_ROOT_PASSWORD}'] = (string) $secrets['minio_root_password'];
        }

        $replacements['${MYSQL_APP_USER:-infra}'] = 'infra';
        $replacements['${POSTGRES_USER:-infra}'] = 'infra';
        $replacements['${SFTP_BOOTSTRAP_USER:-infra}'] = 'infra';

        $mariadbVolume = $secrets['mariadb_volume_name'] ?? null;

        if (is_string($mariadbVolume) && $mariadbVolume !== '') {
            $replacements['${MARIADB_VOLUME_NAME}'] = $mariadbVolume;
        }

        $yaml = str_replace(array_keys($replacements), array_values($replacements), $yaml);

        if (! is_string($mariadbVolume) || $mariadbVolume === '') {
            $stripped = preg_replace('/\n  mariadb:\n    name: \$\{MARIADB_VOLUME_NAME\}\n/', "\n  mariadb:\n", $yaml);
            $yaml = is_string($stripped) ? $stripped : $yaml;
        }

        return $yaml;
    }

    /**
     * @param  array{mysql_root_password: string, mysql_app_password: string, postgres_password: string, sftp_bootstrap_password: string, sftp_sync_token: string, sftp_host_port?: int, pgadmin_email?: string, pgadmin_password?: string, minio_root_user?: string, minio_root_password?: string}  $secrets
     */
    public function envFile(string $slug, array $secrets): string
    {
        $this->assertUsableSecret($secrets['postgres_password'], 'POSTGRES_PASSWORD');
        $this->assertUsableSecret($secrets['mysql_root_password'], 'MYSQL_ROOT_PASSWORD');
        $this->assertUsableSecret($secrets['mysql_app_password'], 'MYSQL_APP_PASSWORD');
        $this->assertUsableSftpPassword($secrets['sftp_bootstrap_password']);
        $this->assertUsableSecret($secrets['sftp_sync_token'], 'SFTP_SYNC_TOKEN');

        $lines = [
            'INFRA_SLUG='.$slug,
            'MYSQL_ROOT_PASSWORD='.$secrets['mysql_root_password'],
            'MYSQL_APP_USER=infra',
            'MYSQL_APP_PASSWORD='.$secrets['mysql_app_password'],
            'POSTGRES_USER=infra',
            'POSTGRES_PASSWORD='.$secrets['postgres_password'],
            'SFTP_BOOTSTRAP_USER=infra',
            'SFTP_BOOTSTRAP_PASSWORD='.$secrets['sftp_bootstrap_password'],
            'SFTP_SYNC_TOKEN='.$secrets['sftp_sync_token'],
            'SFTP_HOST_PORT='.(string) ($secrets['sftp_host_port'] ?? 2222),
        ];

        if (filled($secrets['pgadmin_email'] ?? null) && filled($secrets['pgadmin_password'] ?? null)) {
            $this->assertUsableSecret((string) $secrets['pgadmin_password'], 'PGADMIN_DEFAULT_PASSWORD');
            $lines[] = 'PGADMIN_DEFAULT_EMAIL='.$secrets['pgadmin_email'];
            $lines[] = 'PGADMIN_DEFAULT_PASSWORD='.$secrets['pgadmin_password'];
        }

        if (filled($secrets['minio_root_user'] ?? null) && filled($secrets['minio_root_password'] ?? null)) {
            $this->assertUsableSecret((string) $secrets['minio_root_password'], 'MINIO_ROOT_PASSWORD');
            $lines[] = 'MINIO_ROOT_USER='.$secrets['minio_root_user'];
            $lines[] = 'MINIO_ROOT_PASSWORD='.$secrets['minio_root_password'];
        }

        return implode("\n", $lines)."\n";
    }

    public function phpmyadminHostname(string $slug): string
    {
        return 'pma-'.$slug.'.'.$this->publicHost();
    }

    /**
     * phpMyAdmin Docker requires a fully-qualified URI ending with `/` when behind a reverse proxy.
     */
    public function phpmyadminAbsoluteUri(string $slug, ?string $host = null): string
    {
        if (! filled($host)) {
            return $this->normalizeAbsoluteUri('https://'.$this->phpmyadminHostname($slug));
        }

        $value = strtolower(trim((string) $host));

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = 'https://'.$value;
        }

        return $this->normalizeAbsoluteUri($value);
    }

    public function pgadminHostname(string $slug): string
    {
        return 'pga-'.$slug.'.'.$this->publicHost();
    }

    public function minioHostname(string $slug): string
    {
        return 'minio-'.$slug.'.'.$this->publicHost();
    }

    public function pgadminEmail(string $slug): string
    {
        return 'admin@'.$slug.'.local';
    }

    private function normalizeAbsoluteUri(string $uri): string
    {
        $trimmed = rtrim(trim($uri), '/');

        if ($trimmed === '') {
            throw new \InvalidArgumentException('phpMyAdmin absolute URI cannot be empty.');
        }

        return $trimmed.'/';
    }

    public function publicHost(): string
    {
        $base = strtolower((string) config('dokploy.public_host', 'cloud.silicoreautomation.com'));
        $base = preg_replace('#^https?://#', '', $base) ?? $base;

        return trim($base, '/.');
    }

    private function assertUsableSecret(string $value, string $name): void
    {
        if ($value === '') {
            throw new \InvalidArgumentException($name.' must not be empty.');
        }
    }

    private function assertUsableSftpPassword(string $value): void
    {
        $this->assertUsableSecret($value, 'SFTP_BOOTSTRAP_PASSWORD');

        if (str_contains($value, ':') || str_contains($value, ';')) {
            throw new \InvalidArgumentException('SFTP_BOOTSTRAP_PASSWORD must not contain : or ;.');
        }
    }

    private function removeService(string $yaml, string $service): string
    {
        $key = preg_quote($service, '/');
        $stripped = preg_replace('/\n  '.$key.':\n(?:    .*\n)*/', "\n", $yaml);

        return is_string($stripped) ? $stripped : $yaml;
    }

    private function pruneUnusedVolumes(string $yaml): string
    {
        foreach (['redis', 'minio', 'pgadmin'] as $volume) {
            if (str_contains($yaml, '- '.$volume.':')) {
                continue;
            }

            $stripped = preg_replace('/\n  '.$volume.':(?:\n    name: .*)?\n/', "\n", $yaml);
            $yaml = is_string($stripped) ? $stripped : $yaml;
        }

        return $yaml;
    }

    /**
     * Shipped mode: every service on dokploy-network.
     * isolatedNetworks=true remains only as an unused template branch (do not call from provisioners).
     */
    private function applyNetworkMode(string $yaml, bool $isolatedNetworks): string
    {
        // Rules: false = shared dokploy-network for MariaDB/Postgres/SFTP/etc. (create + updateStack path).
        if (! $isolatedNetworks) {
            return str_replace(
                ['__DB_NET__', '__STORAGE_NET__', '__PMA_DB_NET__', '__PGA_DB_NET__', '__MINIO_STORAGE_NET__', '__EXTRA_NETS__'],
                ['dokploy-network', 'dokploy-network', '', '', '', ''],
                $yaml,
            );
        }

        return str_replace(
            ['__DB_NET__', '__STORAGE_NET__', '__PMA_DB_NET__', '__PGA_DB_NET__', '__MINIO_STORAGE_NET__', '__EXTRA_NETS__'],
            [
                'db',
                'storage',
                "\n      db: {}",
                "\n      db: {}",
                "\n      storage: {}",
                <<<'YAML'

  db:
    name: ${INFRA_SLUG}-db
    external: true
  storage:
    name: ${INFRA_SLUG}-storage
    external: true
YAML,
            ],
            $yaml,
        );
    }

    private function baseYaml(): string
    {
        return <<<'YAML'
# Isolated Deployments MUST be off in Dokploy.
# DB_HOST=${INFRA_SLUG}-mariadb resolves on shared dokploy-network (aliases). Do not create {slug}-db.

services:
  mariadb:
    image: mariadb:11
    hostname: ${INFRA_SLUG}-mariadb
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-changeme}
      MYSQL_ROOT_HOST: "%"
      MYSQL_DATABASE: infra
      MYSQL_USER: ${MYSQL_APP_USER:-infra}
      MYSQL_PASSWORD: ${MYSQL_APP_PASSWORD:-changeme}
    volumes:
      - mariadb:/var/lib/mysql
    networks:
      __DB_NET__:
        aliases:
          - ${INFRA_SLUG}-mariadb
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 12
      start_period: 90s

  mysql-grants:
    image: mariadb:11
    depends_on:
      mariadb:
        condition: service_healthy
    entrypoint: ["sh", "-c"]
    environment:
      MYSQL_PWD: ${MYSQL_ROOT_PASSWORD:-changeme}
      MYSQL_APP_PASSWORD: ${MYSQL_APP_PASSWORD:-changeme}
      INFRA_SLUG: ${INFRA_SLUG:-infra1}
    command:
      - |
        set -e
        echo GRANT_START
        if [ -z "$$MYSQL_APP_PASSWORD" ] || [ -z "$$MYSQL_PWD" ] || [ -z "$$INFRA_SLUG" ]; then
          echo GRANT_FAIL
          exit 1
        fi
        q() { printf "'%s'" "$$(printf '%s' "$$1" | sed "s/'/''/g")"; }
        {
          printf '%s' "CREATE USER IF NOT EXISTS 'infra'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD("
          q "$$MYSQL_APP_PASSWORD"
          printf '%s\n' ");"
          printf '%s' "ALTER USER 'infra'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD("
          q "$$MYSQL_APP_PASSWORD"
          printf '%s\n' ");"
          printf '%s\n' "GRANT ALL PRIVILEGES ON *.* TO 'infra'@'%' WITH GRANT OPTION;"
          printf '%s\n' "FLUSH PRIVILEGES;"
        } > /tmp/grants.sql
        # Rules: MariaDB 11 ignores MYSQL_PWD. -p reads the env inside the container. $$ survives Compose interpolation.
        if mariadb --protocol=tcp -h"$$INFRA_SLUG-mariadb" -uroot -p"$$MYSQL_PWD" < /tmp/grants.sql; then
          echo GRANT_OK
        else
          echo GRANT_FAIL
          exit 1
        fi
    networks:
      - __DB_NET__
    restart: "no"

  phpmyadmin:
    image: phpmyadmin:5
    hostname: ${INFRA_SLUG}-phpmyadmin
    restart: unless-stopped
    environment:
      PMA_HOST: ${INFRA_SLUG}-mariadb
      PMA_PORT: 3306
      # Rules: trailing slash required; without it Traefik HTTPS login loops back to /index.php.
      PMA_ABSOLUTE_URI: ${PMA_ABSOLUTE_URI}
      UPLOAD_LIMIT: 64M
    depends_on:
      mariadb:
        condition: service_healthy
    networks:
      dokploy-network:
        aliases:
          - ${INFRA_SLUG}-phpmyadmin__PMA_DB_NET__

  sftp-users-init:
    image: alpine:3.20
    environment:
      SFTP_BOOTSTRAP_USER: ${SFTP_BOOTSTRAP_USER:-infra}
      SFTP_BOOTSTRAP_PASSWORD: ${SFTP_BOOTSTRAP_PASSWORD:-changeme}
    volumes:
      - sftp_config:/etc/sftp
      - sftp_ssh:/ssh-host-keys
    command:
      - sh
      - -c
      - |
        mkdir -p /etc/sftp /ssh-host-keys
        if [ ! -s /etc/sftp/users.conf ]; then
          printf '%s\n' "${SFTP_BOOTSTRAP_USER:-infra}:${SFTP_BOOTSTRAP_PASSWORD:-changeme}:1001:1001" > /etc/sftp/users.conf
        fi
        if [ ! -f /ssh-host-keys/ssh_host_ed25519_key ]; then
          apk add --no-cache openssh-keygen
          ssh-keygen -t ed25519 -f /ssh-host-keys/ssh_host_ed25519_key -N ""
          ssh-keygen -t rsa -b 4096 -f /ssh-host-keys/ssh_host_rsa_key -N ""
        fi

  sftp-sync:
    image: python:3.12-alpine
    hostname: ${INFRA_SLUG}-sftp-sync
    restart: unless-stopped
    environment:
      SFTP_SYNC_TOKEN: ${SFTP_SYNC_TOKEN:-changeme}
    volumes:
      - data:/data
      - sftp_config:/etc/sftp
    networks:
      __STORAGE_NET__:
        aliases:
          - ${INFRA_SLUG}-sftp-sync
    command:
      - python
      - -c
      - |
        import json, os, http.server
        TOKEN = os.environ.get("SFTP_SYNC_TOKEN", "")
        class H(http.server.BaseHTTPRequestHandler):
            def log_message(self, *args):
                return
            def _ok(self, body=b"ok"):
                self.send_response(200)
                self.end_headers()
                self.wfile.write(body)
            def do_GET(self):
                if self.path == "/health":
                    self._ok()
                    return
                self.send_response(404)
                self.end_headers()
            def do_POST(self):
                if self.path != "/sync" or self.headers.get("Authorization") != "Bearer " + TOKEN:
                    self.send_response(401 if self.path == "/sync" else 404)
                    self.end_headers()
                    return
                n = int(self.headers.get("Content-Length", 0))
                body = json.loads(self.rfile.read(n) or b"{}")
                os.makedirs("/etc/sftp", exist_ok=True)
                lines = []
                for user in body.get("users") or []:
                    name = str(user.get("username", ""))
                    password = str(user.get("password", ""))
                    if name and password and ":" not in name and ":" not in password:
                        lines.append(name + ":" + password + ":1001:1001")
                open("/etc/sftp/users.conf", "w").write(("\n".join(lines) + "\n") if lines else "")
                for directory in body.get("directories") or []:
                    path = os.path.realpath(str(directory))
                    if path == "/data" or path.startswith("/data/"):
                        os.makedirs(path, mode=0o750, exist_ok=True)
                self._ok(b'{"ok":true}')
        http.server.HTTPServer(("0.0.0.0", 8787), H).serve_forever()

  sftp:
    image: atmoz/sftp:alpine
    hostname: ${INFRA_SLUG}-sftp
    restart: unless-stopped
    environment:
      SFTP_BOOTSTRAP_USER: ${SFTP_BOOTSTRAP_USER:-infra}
      SFTP_BOOTSTRAP_PASSWORD: ${SFTP_BOOTSTRAP_PASSWORD:-changeme}
    volumes:
      - data:/home
      - data:/data
      - sftp_config:/etc/sftp
      - sftp_ssh:/ssh-host-keys:ro
    command:
      - sh
      - -c
      - |
        if [ -f /ssh-host-keys/ssh_host_ed25519_key ]; then
          cp /ssh-host-keys/ssh_host_ed25519_key /etc/ssh/ssh_host_ed25519_key
          cp /ssh-host-keys/ssh_host_ed25519_key.pub /etc/ssh/ssh_host_ed25519_key.pub 2>/dev/null || true
        fi
        if [ -f /ssh-host-keys/ssh_host_rsa_key ]; then
          cp /ssh-host-keys/ssh_host_rsa_key /etc/ssh/ssh_host_rsa_key
          cp /ssh-host-keys/ssh_host_rsa_key.pub /etc/ssh/ssh_host_rsa_key.pub 2>/dev/null || true
        fi
        exec /entrypoint "${SFTP_BOOTSTRAP_USER:-infra}:${SFTP_BOOTSTRAP_PASSWORD:-changeme}:1001:1001"
    depends_on:
      sftp-users-init:
        condition: service_completed_successfully
    networks:
      __STORAGE_NET__:
        aliases:
          - ${INFRA_SLUG}-sftp
    ports:
      - "${SFTP_HOST_PORT:-2222}:22"

  postgres:
    image: postgres:16
    hostname: ${INFRA_SLUG}-postgres
    restart: unless-stopped
    environment:
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:-changeme}
      POSTGRES_USER: ${POSTGRES_USER:-infra}
      POSTGRES_DB: infra
    volumes:
      - postgres:/var/lib/postgresql/data
    networks:
      __DB_NET__:
        aliases:
          - ${INFRA_SLUG}-postgres
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER:-infra} -d infra"]
      interval: 10s
      timeout: 5s
      retries: 12
      start_period: 60s

  pgadmin:
    image: dpage/pgadmin4
    hostname: ${INFRA_SLUG}-pgadmin
    restart: unless-stopped
    environment:
      PGADMIN_DEFAULT_EMAIL: ${PGADMIN_DEFAULT_EMAIL:-admin@local}
      PGADMIN_DEFAULT_PASSWORD: ${PGADMIN_DEFAULT_PASSWORD:-changeme}
      PGADMIN_LISTEN_PORT: 80
    depends_on:
      postgres:
        condition: service_healthy
    volumes:
      - pgadmin:/var/lib/pgadmin
    networks:
      dokploy-network:
        aliases:
          - ${INFRA_SLUG}-pgadmin__PGA_DB_NET__

  redis:
    image: redis:7-alpine
    hostname: ${INFRA_SLUG}-redis
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis:/data
    networks:
      __DB_NET__:
        aliases:
          - ${INFRA_SLUG}-redis

  minio:
    image: quay.io/minio/minio
    hostname: ${INFRA_SLUG}-minio
    restart: unless-stopped
    command: ["server", "/data", "--console-address", ":9001"]
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER:-minio}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD:-changeme}
    volumes:
      - minio:/data
    networks:
      dokploy-network:
        aliases:
          - ${INFRA_SLUG}-minio__MINIO_STORAGE_NET__

volumes:
  mariadb:
    name: ${MARIADB_VOLUME_NAME}
  data:
    name: ${INFRA_SLUG}_data
  postgres:
  sftp_config:
  sftp_ssh:
  pgadmin:
  redis:
  minio:

networks:
  dokploy-network:
    external: true__EXTRA_NETS__
YAML;
    }
}
