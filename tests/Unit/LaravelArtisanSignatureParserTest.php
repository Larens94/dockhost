<?php

// LaravelArtisanSignatureParserTest.php — Parses Artisan signatures from PHP source fixtures.
//
// exports: LaravelArtisanSignatureParserTest
// used_by: none
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Command class + console.php fixtures.
// message:

namespace Tests\Unit;

use App\Services\Laravel\LaravelArtisanSignatureParser;
use Tests\TestCase;

class LaravelArtisanSignatureParserTest extends TestCase
{
    public function test_parses_command_class_signature(): void
    {
        $source = <<<'PHP'
        class SyncRuntsCommand extends Command
        {
            protected $signature = 'runts:sync {--force}';
        }
        PHP;

        $this->assertSame(['runts:sync'], (new LaravelArtisanSignatureParser)->fromCommandClassSource($source));
    }

    public function test_parses_console_routes_artisan_command(): void
    {
        $source = <<<'PHP'
        Artisan::command('inspire-hourly', function () {
            $this->comment('Hourly inspiration');
        });
        PHP;

        $this->assertSame(['inspire-hourly'], (new LaravelArtisanSignatureParser)->fromConsoleRoutesSource($source));
    }
}
