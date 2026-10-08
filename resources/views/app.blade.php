{{-- app.blade.php — app template.
--
-- exports: none
-- used_by: none
-- rules:   none
-- agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title inertia>{{ config('app.name', 'Silicore Host') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
        <body class="min-h-screen bg-neutral-50 antialiased">
        @inertia
    </body>
</html>
