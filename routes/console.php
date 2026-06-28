<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// YouTube autofetch — run every 15 minutes
Schedule::command('tam:youtube-autofetch')->everyFifteenMinutes()->withoutOverlapping();

// Newsletter digest — daily at configured times by niche
// Sends top stories to subscribers grouped by their niche preferences
Schedule::command('newsletter:send-digest')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/newsletter-digest.log'));

// Newsletter campaigns — every hour, checks fetch_interval_hours per campaign
Schedule::command('newsletter:send-campaigns')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/newsletter-campaigns.log'));

// Breaking news alerts — every 5 minutes for breaking content
Schedule::command('newsletter:send-breaking')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/newsletter-breaking.log'));
