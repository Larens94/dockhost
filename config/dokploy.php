<?php

// dokploy.php — dokploy module.
//
// exports: none
// used_by: none
// rules:   Dokploy API credentials live only in env — never expose api_key to Inertia/frontend.
//          environment_id is a legacy default; customer Laravel apps use infrastructure.dokploy_environment_id.
//          Panel Application is separate from hosting infra projects (see docs/PLAN.md).
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
//          composer | cursor | 2026-09-21 | s_20260921_codedna | panel-vs-dokploy + secrets rules

return [

    'url' => env('DOKPLOY_URL'),

    'api_key' => env('DOKPLOY_API_KEY'),

    'environment_id' => env('DOKPLOY_ENVIRONMENT_ID'),

    'self_application_id' => env('DOKPLOY_SELF_APPLICATION_ID', env('DOKHOSTS_PANEL_APPLICATION_ID')),

    'panel_application_name' => env('DOKHOSTS_PANEL_APPLICATION_NAME', 'dokhosts'),

    'app_port' => (int) env('DOKPLOY_APP_PORT', 80),

    'public_host' => env('DOKPLOY_PUBLIC_HOST', 'cloud.silicoreautomation.com'),

];
