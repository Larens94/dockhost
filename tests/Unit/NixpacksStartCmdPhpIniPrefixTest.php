<?php

// NixpacksStartCmdPhpIniPrefixTest.php — Unit tests for NIXPACKS_START_CMD PHP ini prefix.
//
// exports: NixpacksStartCmdPhpIniPrefixTest
// used_by: none
// rules:   apply() must not double-prefix; empty command unchanged.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_php_ini_start | Prefix builder coverage.
//          grok-4.7 | cursor | 2026-10-09 | s_ini_sep | Legacy &&__ separator must strip back to mkdir.

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

    public function test_separator_keeps_mkdir_as_its_own_command(): void
    {
        $prefixed = NixpacksStartCmdPhpIniPrefix::apply('mkdir -p /var/log/nginx');

        $this->assertStringContainsString('; : __DOKHOSTS_INI__; mkdir -p /var/log/nginx', $prefixed);
        $this->assertStringNotContainsString('__mkdir', $prefixed);
    }

    public function test_strip_legacy_separator_restores_mkdir(): void
    {
        $legacy = 'printf > "$_dokhosts_ini"__DOKHOSTS_INI__&&__mkdir -p /var/log/nginx';

        $this->assertSame(
            'mkdir -p /var/log/nginx',
            NixpacksStartCmdPhpIniPrefix::stripPrefix($legacy),
        );
    }
}
