<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rate limiters — defined here (not in bootstrap/app.php) to avoid
        // "A facade root has not been set" during composer install on Render.
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('newsletter', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('content-view', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
