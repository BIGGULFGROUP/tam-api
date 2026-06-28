<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\SocialClip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookReelsService
{
    private ?string $appId;
    private ?string $appSecret;
    private ?string $pageId;
    private ?string $pageToken;

    public function __construct()
    {
        $settings = SiteSetting::query()->find(1);
        $this->appId = $settings->facebook_app_id ?? null;
        $this->appSecret = $settings->facebook_app_secret ?? null;
        $this->pageId = $settings->facebook_page_id ?? null;
        $this->pageToken = $settings->facebook_page_token ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->appSecret)
            && !empty($this->pageId) && !empty($this->pageToken);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'error' => 'Facebook credentials not configured.'];
        }

        try {
            $response = Http::get("https://graph.facebook.com/v19.0/{$this->pageId}", [
                'fields' => 'id,name,username,fan_count',
                'access_token' => $this->pageToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'connected' => true,
                    'page_name' => $data['name'] ?? null,
                    'page_username' => $data['username'] ?? null,
                    'fan_count' => $data['fan_count'] ?? 0,
                ];
            }

            return [
                'connected' => false,
                'error' => $response->json()['error']['message'] ?? 'Connection failed',
            ];
        } catch (\Throwable $e) {
            return ['connected' => false, 'error' => $e->getMessage()];
        }
    }

    public function fetchReels(int $maxResults = 20): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'Facebook not configured', 'status' => 400, 'imported' => 0];
        }

        $settings = SiteSetting::query()->find(1);
        $contentFilter = $settings->facebook_content_filter ?? 'reels';

        try {
            $fields = implode(',', [
                'id', 'title', 'description', 'permalink_url',
                'thumbnails{uri,width,height}', 'length',
                'created_time', 'source',
            ]);

            $response = Http::get("https://graph.facebook.com/v19.0/{$this->pageId}/videos", [
                'fields' => $fields,
                'limit' => min($maxResults, 50),
                'access_token' => $this->pageToken,
            ]);

            if (!$response->successful()) {
                Log::error('Facebook fetch failed', ['response' => $response->json()]);
                return [
                    'error' => 'Facebook API error: ' . ($response->json()['error']['message'] ?? 'Unknown'),
                    'status' => 502,
                    'imported' => 0,
                ];
            }

            $videos = $response->json()['data'] ?? [];
            $imported = 0;

            foreach ($videos as $video) {
                $duration = $video['length'] ?? 0;

                // Apply content type filter
                if ($contentFilter === 'reels' && $duration > 90) continue; // Reels ≤ 90s
                if ($contentFilter === 'videos' && $duration <= 90) continue;

                $thumbUrl = null;
                if (!empty($video['thumbnails']['data'])) {
                    $thumbUrl = $video['thumbnails']['data'][0]['uri'] ?? null;
                }

                $exists = SocialClip::where('platform', 'facebook')
                    ->where('external_clip_id', $video['id'])
                    ->exists();

                if ($exists) continue;

                SocialClip::create([
                    'platform' => 'facebook',
                    'external_clip_id' => $video['id'],
                    'title' => $video['title'] ?? 'Untitled Reel',
                    'caption' => $video['description'] ?? null,
                    'thumbnail_url' => $thumbUrl,
                    'clip_url' => $video['permalink_url'] ?? null,
                    'embed_url' => "https://www.facebook.com/plugins/video.php?href=" . urlencode($video['permalink_url'] ?? ''),
                    'duration_seconds' => $duration > 0 ? $duration : null,
                    'fetched_at' => now(),
                    'published_at' => isset($video['created_time']) ? \Carbon\Carbon::parse($video['created_time']) : null,
                    'platform_metadata' => [
                        'source' => $video['source'] ?? null,
                        'graph_api_version' => 'v19.0',
                    ],
                    'mapping_status' => 'unlinked',
                ]);

                $imported++;
            }

            return [
                'imported' => $imported,
                'total_fetched' => count($videos),
                'status' => 200,
            ];
        } catch (\Throwable $e) {
            Log::error('Facebook fetch exception', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage(), 'status' => 500, 'imported' => 0];
        }
    }

    public function refreshLongLivedToken(): array
    {
        if (empty($this->appId) || empty($this->appSecret) || empty($this->pageToken)) {
            return ['error' => 'Missing credentials', 'status' => 400];
        }

        try {
            // Exchange short-lived for long-lived token
            $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $this->appId,
                'client_secret' => $this->appSecret,
                'fb_exchange_token' => $this->pageToken,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $settings = SiteSetting::query()->find(1);
                $settings->facebook_page_token = $data['access_token'];
                $settings->facebook_token_expires_at = isset($data['expires_in'])
                    ? now()->addSeconds($data['expires_in'])
                    : null;
                $settings->save();

                return [
                    'refreshed' => true,
                    'expires_in' => $data['expires_in'] ?? null,
                    'expires_at' => $settings->facebook_token_expires_at?->toIso8601String(),
                ];
            }

            return ['refreshed' => false, 'error' => $response->json()['error']['message'] ?? 'Token refresh failed'];
        } catch (\Throwable $e) {
            return ['refreshed' => false, 'error' => $e->getMessage()];
        }
    }
}
