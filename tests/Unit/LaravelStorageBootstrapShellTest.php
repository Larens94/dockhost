<?php

namespace Tests\Unit;

use App\Support\LaravelStorageBootstrapShell;
use PHPUnit\Framework\TestCase;

class LaravelStorageBootstrapShellTest extends TestCase
{
    public function test_weave_inserts_marker_after_prepare(): void
    {
        $core = 'mkdir -p storage && chmod -R a+rwx storage bootstrap/cache && php artisan migrate --force';
        $woven = LaravelStorageBootstrapShell::weaveIntoCore($core);

        $this->assertTrue(LaravelStorageBootstrapShell::isConfigured($woven));
        $this->assertStringContainsString('rm -rf storage/app/public', $woven);
        $this->assertStringContainsString('ln -sfn "$_dokhosts_storage/storage/app/public" storage/app/public', $woven);
        $this->assertMatchesRegularExpression(
            '/chmod -R a\+rwx storage bootstrap\/cache && .*php artisan migrate/',
            $woven,
        );
    }

    public function test_weave_is_idempotent(): void
    {
        $core = LaravelStorageBootstrapShell::weaveIntoCore('echo start');

        $this->assertSame($core, LaravelStorageBootstrapShell::weaveIntoCore($core));
    }
}
