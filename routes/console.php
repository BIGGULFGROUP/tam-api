<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// YouTube autofetch — run every 15 minutes
Schedule::command('tam:youtube-autofetch')->everyFifteenMinutes()->withoutOverlapping();
