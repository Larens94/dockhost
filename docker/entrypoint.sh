#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required at runtime." >&2
    exit 1
fi

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ -d /data ]; then
    mkdir -p /data
    chmod 0775 /data || true
fi

if [ -d /etc/sftp ]; then
    chmod 0775 /etc/sftp || true
    if [ ! -f /etc/sftp/users.conf ]; then
        touch /etc/sftp/users.conf
    fi
    chmod 0664 /etc/sftp/users.conf || true
fi

chown -R www-data:www-data storage bootstrap/cache || true

php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    db_path="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
    mkdir -p "$(dirname "$db_path")"
    if [ ! -f "$db_path" ]; then
        touch "$db_path"
    fi
    chown www-data:www-data "$db_path" || true
fi

if [ "${DB_CONNECTION:-}" = "mysql" ]; then
    echo "Waiting for MySQL at ${DB_HOST:-}:${DB_PORT:-3306}..."
    i=0
    until php -r '
        $host = getenv("DB_HOST") ?: "";
        $port = getenv("DB_PORT") ?: "3306";
        try {
            new PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                getenv("DB_USERNAME") ?: "",
                getenv("DB_PASSWORD") ?: ""
            );
            exit(0);
        } catch (Throwable $e) {
            fwrite(STDERR, $e->getMessage().PHP_EOL);
            exit(1);
        }
    '; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "MySQL is not reachable at ${DB_HOST:-}:${DB_PORT:-3306} after 60s." >&2
            exit 1
        fi
        sleep 2
    done
fi

php artisan migrate --force
php artisan dokhosts:sync-panel-gitlab-env --no-interaction || true
php artisan db:seed --force
php artisan infra:align-mysql-from-compose --prune-fqdn=test.vibesbridge.com --no-interaction || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart

exec apache2-foreground
