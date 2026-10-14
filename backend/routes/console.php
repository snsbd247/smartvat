<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Encrypted, verified database backup every 20 minutes (requires the scheduler:
// `* * * * * php artisan schedule:run` on the server).
Schedule::command('backup:run')->cron('*/20 * * * *')->withoutOverlapping();
