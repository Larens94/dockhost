<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteToolkitController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WizardController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'superadmin'])->group(function () {
    Route::get('/dashboard', [PanelController::class, 'dashboard'])->name('dashboard');

    Route::get('/clients', [PanelController::class, 'clients'])->name('clients.index');
    Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create');
    Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit');
    Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

    Route::get('/sites', [PanelController::class, 'sites'])->name('sites.index');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::get('/sites/{site}/toolkit', [SiteToolkitController::class, 'show'])->name('sites.toolkit');
    Route::patch('/sites/{site}/toolkit', [SiteToolkitController::class, 'updateSettings'])->name('sites.toolkit.update');
    Route::post('/sites/{site}/artisan', [SiteToolkitController::class, 'runArtisan'])->name('sites.artisan');

    Route::get('/wizard', [WizardController::class, 'create'])->name('wizard.create');
    Route::post('/wizard', [WizardController::class, 'store'])->name('wizard.store');

    Route::get('/pools', [PanelController::class, 'pools'])->name('pools.index');
    Route::get('/servers', [PanelController::class, 'servers'])->name('servers.index');
    Route::post('/servers/sync', [PanelController::class, 'syncServers'])->name('servers.sync');
    Route::get('/services', [PanelController::class, 'services'])->name('services.index');
    Route::get('/recipes', [PanelController::class, 'recipes'])->name('recipes.index');
    Route::get('/templates', [PanelController::class, 'templates'])->name('templates.index');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/assign', [BillingController::class, 'assign'])->name('billing.assign');

    Route::get('/settings/dokploy', [PanelController::class, 'dokploySettings'])->name('settings.dokploy');
    Route::post('/settings/dokploy/ping', [PanelController::class, 'pingDokploy'])->name('settings.dokploy.ping');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
