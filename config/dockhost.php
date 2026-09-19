<?php

return [
    /*
    | DockHost is the control panel. Recipes are installable applications
    | (Laravel is the first app in the catalog, not the panel identity).
    |
    */

    'driver' => env('DOCKHOST_DRIVER', 'dokploy'),

    'dokploy' => [
        'url' => env('DOKPLOY_URL'),
        'api_key' => env('DOKPLOY_API_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    | Features owned by Dokploy — do not rebuild in DockHost UI.
    */
    'dokploy_owns' => [
        'deployments',
        'build_logs',
        'runtime_logs',
        'ssl_certificates',
        'cron_schedules',
        'docker_inspect',
        'traefik_files',
    ],
];
