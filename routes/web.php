<?php

// web.php — web module.
//
// exports: route:login | route:logout | route:domains | route:domains/{domain} | (+ admin resources)
// used_by: none
// rules:   Admin-only: infrastructures, anagrafica, domain destroy, GitLab PAT, SMTP principale.
//          Domain owners manage Accessi on their domain; members mutate per pivot role (readonly = view only).
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_acl | Split admin vs domain-member routes.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_iam | Member routes + password reset guest routes.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_domain_php | POST domains/{domain}/php-settings.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_smtp | Admin panel/smtp SMTP principale routes.
// agent:   composer-2.5-fast | cursor | 2026-09-24 | s_panel_2fa_audit | 2FA account routes + admin audit log.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_domain_site_env | POST site-env + deploy for domain members.
// agent:   composer-2.5-fast | cursor | 2026-09-25 | s_smtp_test | POST panel/smtp/test admin SMTP test mail.

use App\Http\Controllers\Account\TwoFactorAuthenticationController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DomainMemberController;
use App\Http\Controllers\InfrastructureController;
use App\Http\Controllers\LaravelToolkitController;
use App\Http\Controllers\PanelGitLabCredentialController;
use App\Http\Controllers\PanelSmtpSettingsController;
use App\Http\Controllers\ServicePlanController;
use App\Http\Controllers\SubscriptionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->get('/', function (Request $request) {
    $user = $request->user();

    if ($user instanceof User && ! $user->isAdmin()) {
        return redirect()->route('domains.index');
    }

    return redirect()->route('customers.index');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('login/two-factor', [TwoFactorChallengeController::class, 'create'])->name('two-factor.login');
    Route::post('login/two-factor', [TwoFactorChallengeController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('account/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('account.two-factor.show');
    Route::post('account/two-factor/prepare', [TwoFactorAuthenticationController::class, 'prepare'])
        ->name('account.two-factor.prepare');
    Route::post('account/two-factor/confirm', [TwoFactorAuthenticationController::class, 'confirm'])
        ->name('account.two-factor.confirm');
    Route::delete('account/two-factor', [TwoFactorAuthenticationController::class, 'destroy'])
        ->name('account.two-factor.destroy');

    Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
    Route::get('domains/{domain}', [DomainController::class, 'show'])->name('domains.show');

    Route::post('domains/{domain}/database', [DomainController::class, 'storeDatabase'])
        ->name('domains.database.store');
    Route::post('domains/{domain}/database-users', [DomainController::class, 'storeDatabaseUser'])
        ->name('domains.database-users.store');
    Route::post('domains/{domain}/sftp-users', [DomainController::class, 'storeSftpUser'])
        ->name('domains.sftp-users.store');
    Route::post('domains/{domain}/laravel', [DomainController::class, 'attachLaravel'])
        ->name('domains.laravel.store');
    Route::post('domains/{domain}/laravel/align-env', [DomainController::class, 'alignLaravelEnv'])
        ->name('domains.laravel.align-env');
    Route::post('domains/{domain}/laravel/deploy-config', [DomainController::class, 'applyLaravelDeployConfig'])
        ->name('domains.laravel.deploy-config');
    Route::get('domains/{domain}/laravel/status', [LaravelToolkitController::class, 'status'])
        ->name('domains.laravel.status');
    Route::post('domains/{domain}/laravel/artisan', [LaravelToolkitController::class, 'artisan'])
        ->name('domains.laravel.artisan');
    Route::post('domains/{domain}/laravel/composer', [LaravelToolkitController::class, 'composer'])
        ->name('domains.laravel.composer');
    Route::post('domains/{domain}/laravel/npm', [LaravelToolkitController::class, 'npm'])
        ->name('domains.laravel.npm');
    Route::post('domains/{domain}/stack-preset', [DomainController::class, 'applyStackPreset'])
        ->name('domains.stack-preset');
    Route::post('domains/{domain}/php-settings', [DomainController::class, 'updatePhpSettings'])
        ->name('domains.php-settings.update');
    Route::post('domains/{domain}/site-env', [DomainController::class, 'updateSiteEnv'])
        ->name('domains.site-env.update');
    Route::post('domains/{domain}/deploy', [DomainController::class, 'deploySite'])
        ->name('domains.deploy');

    Route::post('domains/{domain}/members', [DomainMemberController::class, 'store'])
        ->name('domains.members.store');
    Route::put('domains/{domain}/members/{user}', [DomainMemberController::class, 'update'])
        ->name('domains.members.update');
    Route::delete('domains/{domain}/members/{user}', [DomainMemberController::class, 'destroy'])
        ->name('domains.members.destroy');

    Route::middleware('admin')->group(function () {
        Route::resource('infrastructures', InfrastructureController::class)->only([
            'index',
            'create',
            'store',
            'show',
            'update',
            'destroy',
        ]);
        Route::post('infrastructures/{infrastructure}/phpmyadmin-domain', [InfrastructureController::class, 'attachPhpmyadmin'])
            ->name('infrastructures.phpmyadmin.store');
        Route::post('infrastructures/{infrastructure}/pgadmin-domain', [InfrastructureController::class, 'attachPgadmin'])
            ->name('infrastructures.pgadmin.store');
        Route::post('infrastructures/{infrastructure}/minio-domain', [InfrastructureController::class, 'attachMinio'])
            ->name('infrastructures.minio.store');
        Route::post('infrastructures/{infrastructure}/database-users', [InfrastructureController::class, 'storeDatabaseUser'])
            ->name('infrastructures.database-users.store');
        Route::post('infrastructures/{infrastructure}/sftp-users', [InfrastructureController::class, 'storeSftpUser'])
            ->name('infrastructures.sftp-users.store');
        Route::get('infrastructures/{infrastructure}/deploy-status', [InfrastructureController::class, 'deployStatus'])
            ->name('infrastructures.deploy-status');
        Route::get('infrastructures/{infrastructure}/dokploy-inspect', [InfrastructureController::class, 'inspect'])
            ->name('infrastructures.dokploy-inspect');
        Route::post('infrastructures/{infrastructure}/reset-mysql', [InfrastructureController::class, 'resetMysqlDatadir'])
            ->name('infrastructures.mysql.reset');

        Route::resource('customers', CustomerController::class)->only([
            'index',
            'create',
            'store',
            'show',
            'update',
            'destroy',
        ]);

        Route::resource('service-plans', ServicePlanController::class)->only([
            'index',
            'create',
            'store',
            'show',
        ]);

        Route::resource('subscriptions', SubscriptionController::class)->only([
            'index',
            'create',
            'store',
            'show',
        ]);

        Route::post('customers/{customer}/subscriptions', [SubscriptionController::class, 'store'])
            ->name('customers.subscriptions.store');

        Route::get('subscriptions/{subscription}/domains/create', [DomainController::class, 'create'])
            ->name('subscriptions.domains.create');
        Route::post('subscriptions/{subscription}/domains', [DomainController::class, 'store'])
            ->name('subscriptions.domains.store');

        Route::put('domains/{domain}', [DomainController::class, 'update'])
            ->name('domains.update');
        Route::delete('domains/{domain}', [DomainController::class, 'destroy'])
            ->name('domains.destroy');

        Route::get('laravel-toolkit', [LaravelToolkitController::class, 'index'])
            ->name('laravel-toolkit.index');

        Route::post('panel/gitlab/credentials', [PanelGitLabCredentialController::class, 'store'])
            ->name('panel.gitlab.credentials.store');

        Route::get('panel/smtp', [PanelSmtpSettingsController::class, 'index'])
            ->name('panel.smtp.index');
        Route::post('panel/smtp', [PanelSmtpSettingsController::class, 'update'])
            ->name('panel.smtp.update');
        Route::post('panel/smtp/redeploy', [PanelSmtpSettingsController::class, 'redeploy'])
            ->name('panel.smtp.redeploy');
        Route::post('panel/smtp/test', [PanelSmtpSettingsController::class, 'test'])
            ->name('panel.smtp.test');

        Route::get('panel/audit', [AuditLogController::class, 'index'])
            ->name('panel.audit.index');
    });
});
