<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    private const CACHE_TTL = 86400 * 30; // 30 days — IP-to-country mappings are stable
    private const API_URL = 'http://ip-api.com/json/%s?fields=countryCode,country';

    /**
     * Resolve an IP address to country code + name.
     * Uses local cache and ip-api.com (free tier: 45 req/min).
     *
     * @return array{code: string|null, name: string|null}
     */
    public function resolve(string $ip): array
    {
        // Skip private/reserved IPs
        if ($this->isPrivate($ip)) {
            return ['code' => null, 'name' => null];
        }

        $cacheKey = "ipgeo:{$ip}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($ip) {
            try {
                $response = Http::timeout(3)
                    ->retry(1, 200)
                    ->get(sprintf(self::API_URL, $ip));

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['countryCode'] ?? null) && ($data['country'] ?? null)) {
                        return [
                            'code' => strtoupper($data['countryCode']),
                            'name' => $data['country'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("IP geolocation failed for {$ip}: {$e->getMessage()}");
            }

            return ['code' => null, 'name' => null];
        });
    }

    private function isPrivate(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
