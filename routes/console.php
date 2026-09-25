<?php

use App\Models\Pool;
use App\Services\PoolLedger;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('dockhost:refresh-deploys')->everyMinute();

Artisan::command('dockhost:reconcile-usage', function () {
    app(PoolLedger::class)->recalculate(Pool::query()->pluck('id')->all());
    $this->info('Pool usage recalculated from sites that still hold capacity.');
})->purpose('Recalculate pool usage from held site attachments');
