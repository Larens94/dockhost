<?php

// LaravelToolkitCommandAllowlistTest.php — Allowlist validation for fallback catalog commands.
//
// exports: LaravelToolkitCommandAllowlistTest
// used_by: none
// rules:   none
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_git_toolkit_disc | Fallback-only artisan/composer/npm helpers.
// message:

namespace Tests\Unit;

use App\Services\Laravel\LaravelToolkitCommandAllowlist;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LaravelToolkitCommandAllowlistTest extends TestCase
{
    #[DataProvider('artisanAccepted')]
    public function test_accepts_allowlisted_artisan_commands(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->app->make(LaravelToolkitCommandAllowlist::class)->artisanWithFallbackCatalog($raw));
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function artisanAccepted(): array
    {
        return [
            ['migrate --force', 'migrate --force'],
            ['migrate:status', 'migrate:status'],
            ['php artisan migrate:status', 'migrate:status'],
            ['php artisan optimize:clear', 'optimize:clear'],
            ['queue:restart', 'queue:restart'],
            ['storage:link', 'storage:link'],
            ['schedule:list', 'schedule:list'],
            ['route:list', 'route:list'],
            ['config:cache', 'config:cache'],
            ['migrate:rollback --force', 'migrate:rollback --force'],
            ['inertia:check-ssr', 'inertia:check-ssr'],
            ['db:seed', 'db:seed'],
            ['migrate --seed --force', 'migrate --seed --force'],
        ];
    }

    #[DataProvider('artisanRejected')]
    public function test_rejects_artisan_outside_allowlist(string $raw): void
    {
        $this->expectException(ValidationException::class);

        $this->app->make(LaravelToolkitCommandAllowlist::class)->artisanWithFallbackCatalog($raw);
    }

    /**
     * @return list<list<string>>
     */
    public static function artisanRejected(): array
    {
        return [
            ['tinker'],
            ['migrate:fresh --force'],
            ['migrate --force; rm -rf /'],
            ['db'],
            ['about | cat'],
            ['runts:import'],
            ['runts:import relative/path.csv'],
            ['runts:import /etc/../passwd'],
            ['runts:enrich --shell=1'],
        ];
    }

    public function test_accepts_allowlisted_composer_commands(): void
    {
        $allowlist = $this->app->make(LaravelToolkitCommandAllowlist::class);

        $this->assertSame('install --no-dev', $allowlist->composerWithFallbackCatalog('composer install --no-dev'));
        $this->assertSame('dump-autoload', $allowlist->composerWithFallbackCatalog('dump-autoload'));
    }

    public function test_rejects_composer_update(): void
    {
        $this->expectException(ValidationException::class);

        $this->app->make(LaravelToolkitCommandAllowlist::class)->composerWithFallbackCatalog('update');
    }

    public function test_accepts_allowlisted_npm_commands(): void
    {
        $allowlist = $this->app->make(LaravelToolkitCommandAllowlist::class);

        $this->assertSame('ci', $allowlist->npmWithFallbackCatalog('npm ci'));
        $this->assertSame('run build', $allowlist->npmWithFallbackCatalog('run build'));
    }

    public function test_rejects_npm_run_dev(): void
    {
        $this->expectException(ValidationException::class);

        $this->app->make(LaravelToolkitCommandAllowlist::class)->npmWithFallbackCatalog('run dev');
    }
}
