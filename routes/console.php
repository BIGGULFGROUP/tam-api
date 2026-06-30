<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// YouTube autofetch — run every 15 minutes
Schedule::command('tam:youtube-autofetch')->everyFifteenMinutes()->withoutOverlapping();

// Newsletter — daily digest, hourly campaign check, breaking news every 5 minutes
Schedule::command('newsletter:send-digest')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('newsletter:send-campaigns')->hourly()->withoutOverlapping();
Schedule::command('newsletter:send-breaking')->everyFiveMinutes()->withoutOverlapping();
