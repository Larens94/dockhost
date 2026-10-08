<?php

// AppServiceProvider.php — AppServiceProvider module.
//
// exports: AppServiceProvider | AppServiceProvider::register(): void | AppServiceProvider::boot(): void
// used_by: tests/Feature/AuthenticationTest.php
// rules:   mergeStoredIntoConfig runs in boot when GITLAB_* env empty — DB PAT for Toolkit.
// agent:   composer-2.5-fast | cursor | 2026-09-23 | s_gitlab_ui_connect | Load encrypted GitLab token from panel_settings.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | Load panel SMTP from panel_settings when MAIL_PASSWORD env empty.
// agent:   codedna-cli (no-llm) | codedna-cli | 2026-09-21 | codedna-cli | initial CodeDNA annotation pass
// message:

namespace App\Providers;

use App\Models\Domain;
use App\Models\User;
use App\Services\Infra\ComposeMysqlCredentialAligner;
use App\Services\Infra\MysqlProvisioner;
use App\Services\Infra\PostgresProvisioner;
use App\Services\Panel\PanelGitLabCredentialStore;
use App\Services\Panel\PanelSmtpSettingsStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MysqlProvisioner::class, function (Application $app): MysqlProvisioner {
            return new MysqlProvisioner(
                function (): PDO {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;charset=utf8mb4',
                        config('infra.mysql.host'),
                        config('infra.mysql.port'),
                    );

                    return new PDO(
                        $dsn,
                        (string) config('infra.mysql.username'),
                        (string) config('infra.mysql.password'),
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                    );
                },
                $app->make(ComposeMysqlCredentialAligner::class),
            );
        });

        $this->app->singleton(PostgresProvisioner::class, function (): PostgresProvisioner {
            return new PostgresProvisioner(function (): PDO {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%d;dbname=%s',
                    config('infra.postgres.host'),
                    config('infra.postgres.port'),
                    config('infra.postgres.database'),
                );

                return new PDO(
                    $dsn,
                    (string) config('infra.postgres.username'),
                    (string) config('infra.postgres.password'),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );
            });
        });
    }

    public function boot(): void
    {
        $this->app->make(PanelGitLabCredentialStore::class)->mergeStoredIntoConfig();
        $this->app->make(PanelSmtpSettingsStore::class)->mergeStoredIntoConfig();

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Route::bind('domain', function (string $value): Domain {
            $domain = Domain::query()->whereKey($value)->firstOrFail();
            $user = Auth::user();

            if ($user instanceof User && ! $user->canAccessDomain($domain)) {
                abort(404);
            }

            return $domain;
        });
    }
}
