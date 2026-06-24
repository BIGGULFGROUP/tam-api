<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Middleware\EnsureAdminPanelAccess;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum token authentication for all API routes
        $middleware->statefulApi();

        $middleware->alias([
            'admin.panel' => EnsureAdminPanelAccess::class,
        ]);

        // Allow the admin secret header through without CSRF checks
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Rate limiters
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('newsletter', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('content-view', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Always return JSON for API routes
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
