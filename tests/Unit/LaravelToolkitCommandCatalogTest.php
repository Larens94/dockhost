<?php

// LaravelToolkitCommandCatalogTest.php — Ensures fallback catalog entries pass allowlist validation.
//
// exports: LaravelToolkitCommandCatalogTest
// used_by: none
// rules:   Fallback UI presets must match allowlist or Toolkit POST will 422.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Uses fallback helpers on allowlist.
// message:

namespace Tests\Unit;

use App\Services\Laravel\LaravelToolkitCommandAllowlist;
use App\Services\Laravel\LaravelToolkitCommandCatalog;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LaravelToolkitCommandCatalogTest extends TestCase
{
    public function test_every_fallback_artisan_preset_is_allowlisted(): void
    {
        $catalog = new LaravelToolkitCommandCatalog;
        $allowlist = $this->app->make(LaravelToolkitCommandAllowlist::class);

        foreach ($catalog->fallbackForUi()['artisan'] as $category) {
            foreach ($category['commands'] as $preset) {
                if (($preset['exec'] ?? true) === false) {
                    continue;
                }

                $this->assertSame(
                    strtolower(trim($preset['command'])),
                    strtolower($allowlist->artisanWithFallbackCatalog($preset['command'])),
                    'Artisan preset rejected: '.$preset['command'],
                );
            }
        }
    }

    public function test_every_fallback_composer_preset_is_allowlisted(): void
    {
        $catalog = new LaravelToolkitCommandCatalog;
        $allowlist = $this->app->make(LaravelToolkitCommandAllowlist::class);

        foreach ($catalog->fallbackForUi()['composer'] as $category) {
            foreach ($category['commands'] as $preset) {
                $this->assertSame(
                    strtolower(trim($preset['command'])),
                    strtolower($allowlist->composerWithFallbackCatalog($preset['command'])),
                    'Composer preset rejected: '.$preset['command'],
                );
            }
        }
    }

    public function test_every_exec_npm_preset_is_allowlisted(): void
    {
        $catalog = new LaravelToolkitCommandCatalog;
        $allowlist = $this->app->make(LaravelToolkitCommandAllowlist::class);

        foreach ($catalog->fallbackForUi()['npm'] as $category) {
            foreach ($category['commands'] as $preset) {
                if (($preset['exec'] ?? true) === false) {
                    continue;
                }

                $this->assertSame(
                    strtolower(trim($preset['command'])),
                    strtolower($allowlist->npmWithFallbackCatalog($preset['command'])),
                    'npm preset rejected: '.$preset['command'],
                );
            }
        }
    }

    public function test_npm_run_dev_is_not_allowlisted(): void
    {
        $this->expectException(ValidationException::class);

        $this->app->make(LaravelToolkitCommandAllowlist::class)->npmWithFallbackCatalog('run dev');
    }
}
