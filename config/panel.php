<?php

// panel.php — Panel-wide limits and feature flags (not per-customer site PHP).
//
// exports: none
// used_by: app/Support/PanelDatabaseImportLimits.php
//         app/Http/Requests/ImportDomainDatabaseDumpRequest.php
// rules:   database_import_max_kilobytes is the app cap; effective limit is min(PHP ini, this cap).
// agent:   composer-2.5-fast | cursor | 2026-10-12 | s_db_import_limits | Panel SQL import upload caps
// message:

return [

    /*
    |--------------------------------------------------------------------------
    | SQL dump import (Domains → Database tab)
    |--------------------------------------------------------------------------
    |
    | Kilobytes. Capped by upload_max_filesize and post_max_size in the panel container.
    |
    */

    'database_import_max_kilobytes' => (int) env('PANEL_DATABASE_IMPORT_MAX_KB', 102400),

];
