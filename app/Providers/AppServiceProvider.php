<?php

namespace App\Providers;

use App\Contracts\InfrastructureDriver;
use App\Infrastructure\DokployDriver;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
