<?php

$appHost = parse_url((string) config('app.url'), PHP_URL_HOST) ?: null;

return [
    'frontend_domain' => env('FRONTEND_ADMIN_API_DOMAIN', $appHost),
    'backend_domain' => env('BACKEND_ADMIN_API_DOMAIN'),
    'frontend_prefix' => env('FRONTEND_ADMIN_API_PREFIX', 'frontend-admin'),
    'backend_prefix' => env('BACKEND_ADMIN_API_PREFIX', 'backend-admin'),
];
