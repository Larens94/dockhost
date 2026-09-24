<?php

namespace App\Providers;

use App\Contracts\DatabaseAdmin;
use App\Contracts\InfrastructureDriver;
use App\Contracts\RuntimeAdmin;
use App\Infrastructure\DokployDriver;
use App\Infrastructure\PdoDatabaseAdmin;
use App\Infrastructure\SshRuntimeAdmin;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(InfrastructureDriver::class, function () {
            return match (config('dockhost.driver')) {
                default => new DokployDriver,
            };
        });

        $this->app->bind(DatabaseAdmin::class, PdoDatabaseAdmin::class);
        $this->app->bind(RuntimeAdmin::class, SshRuntimeAdmin::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
