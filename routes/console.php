<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily incremental scrape: new + upcoming events, plus their fights and fighters.
Schedule::command('ufc:scrape')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground();
