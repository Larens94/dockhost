<?php

namespace App\Providers;

use App\Contracts\DatabaseAdmin;
use App\Contracts\InfrastructureDriver;
use App\Infrastructure\DokployDriver;
use App\Infrastructure\PdoDatabaseAdmin;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
