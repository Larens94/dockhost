<?php


// infra.php — infra module.
//
// exports: none
// used_by: none
// rules:   Admin MySQL/Postgres passwords are env-only — never return them to Inertia.
//          Hostnames like infra1-mariadb are defaults; real hosts come from Infrastructure models.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | secrets + hostname rules 

return [

    'storage_root' => env('INFRA_STORAGE_ROOT', '/data'),

    'mysql' => [
        'host' => env('INFRA_MYSQL_HOST', 'infra1-mariadb'),
        'port' => (int) env('INFRA_MYSQL_PORT', 3306),
        'username' => env('INFRA_MYSQL_ADMIN_USER'),
        'password' => env('INFRA_MYSQL_ADMIN_PASSWORD'),
    ],

    'postgres' => [
        'host' => env('INFRA_POSTGRES_HOST', 'infra1-postgres'),
        'port' => (int) env('INFRA_POSTGRES_PORT', 5432),
        'username' => env('INFRA_POSTGRES_ADMIN_USER'),
        'password' => env('INFRA_POSTGRES_ADMIN_PASSWORD'),
        'database' => env('INFRA_POSTGRES_ADMIN_DATABASE', 'postgres'),
    ],

    'sftp' => [
        'host' => env('INFRA_SFTP_HOST', 'infra1-sftp'),
        'users_file' => env('INFRA_SFTP_USERS_FILE', '/etc/sftp/users.conf'),
        'sync_url' => env('INFRA_SFTP_SYNC_URL', 'http://{slug}-sftp-sync:8787/sync'),
    ],

];
