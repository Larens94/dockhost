<?php

// NixpacksStartCmdPhpIniPrefixTest.php — Unit tests for NIXPACKS_START_CMD PHP ini prefix.
//
// exports: NixpacksStartCmdPhpIniPrefixTest
// used_by: none
// rules:   apply() must not double-prefix; empty command unchanged.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_php_ini_start | Prefix builder coverage.

namespace Tests\Unit;

use App\Support\NixpacksStartCmdPhpIniPrefix;
use PHPUnit\Framework\TestCase;

class NixpacksStartCmdPhpIniPrefixTest extends TestCase
{
    public function test_apply_leaves_empty_command_unchanged(): void
    {
        $this->assertSame('', NixpacksStartCmdPhpIniPrefix::apply(''));
        $this->assertNull(NixpacksStartCmdPhpIniPrefix::apply(null));
    }

    public function test_apply_prefixes_core_command_once(): void
    {
        $core = 'php artisan migrate --force && nginx -c /nginx.conf';
        $once = NixpacksStartCmdPhpIniPrefix::apply($core);

        $this->assertStringContainsString(NixpacksStartCmdPhpIniPrefix::SEPARATOR, $once);
        $this->assertStringContainsString('dokhosts.ini', $once);
        $this->assertStringEndsWith($core, $once);

        $twice = NixpacksStartCmdPhpIniPrefix::apply($once);
        $this->assertSame($once, $twice);
        $this->assertSame(1, substr_count($twice, NixpacksStartCmdPhpIniPrefix::SEPARATOR));
    }

    public function test_strip_prefix_restores_core(): void
    {
        $core = 'npm start';
        $prefixed = NixpacksStartCmdPhpIniPrefix::apply($core);

        $this->assertSame($core, NixpacksStartCmdPhpIniPrefix::stripPrefix($prefixed));
    }
}
