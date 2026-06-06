<?php

use App\Domain\Catalog\Jobs\SyncZohoProducts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Catalog: incremental Zoho product sync every 30 min, full sync nightly.
Schedule::job(new SyncZohoProducts(full: false))->everyThirtyMinutes()->withoutOverlapping();
Schedule::job(new SyncZohoProducts(full: true))->dailyAt('02:00')->withoutOverlapping();
