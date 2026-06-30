<?php

namespace App\Support;

class PublicUrl
{
    /**
     * Build a link to the public-facing website (tam-web), never the API's
     * own APP_URL. Laravel's bare url() helper resolves against APP_URL,
     * which is this API's own domain — using it for outbound email links
     * previously sent every newsletter link to the API host instead of the
     * site. This is the only sanctioned way to build a link for an email.
     */
    public static function to(string $path = '/'): string
    {
        $base = rtrim(static::base(), '/');
        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }

    protected static function base(): string
    {
        $configured = config('services.frontend_public_url');
        if ($configured) {
            return $configured;
        }

        // Fall back to the first entry in the comma-separated FRONTEND_URL
        // (used for CORS) if FRONTEND_PUBLIC_URL was never set.
        $frontendUrl = (string) env('FRONTEND_URL', '');
        $first = trim(explode(',', $frontendUrl)[0] ?? '');

        return $first ?: 'https://theafricanmail.com';
    }
}
