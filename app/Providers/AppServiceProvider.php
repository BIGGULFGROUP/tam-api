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

        // Use the real client IP from X-Forwarded-For when the request comes
        // through a trusted proxy (e.g. the Next.js admin server on Render).
        // This prevents all Next.js server traffic from sharing one rate-limit bucket.
        $clientIp = fn (Request $r): string =>
            $r->header('X-Forwarded-For')
                ? trim(explode(',', $r->header('X-Forwarded-For'))[0])
                : $r->ip();

        // Global API limiter — keyed by real client IP, generous for server-to-server.
        RateLimiter::for('api', fn (Request $r) =>
            $r->user()
                ? Limit::none()
                : Limit::perMinute(300)->by($clientIp($r))
        );

        // Login — keyed by email so one user can't brute-force from many IPs.
        RateLimiter::for('login', fn (Request $r) =>
            Limit::perMinute(10)->by(strtolower((string) $r->input('email')) . '|' . $clientIp($r))
        );

        RateLimiter::for('comments', fn (Request $r) => Limit::perMinute(5)->by($clientIp($r)));
        RateLimiter::for('newsletter', fn (Request $r) => Limit::perMinute(3)->by($clientIp($r)));
        RateLimiter::for('content-view', fn (Request $r) => Limit::perMinute(60)->by($clientIp($r)));
    }
}
