<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\SocialClip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TiktokVideoService
{
    private ?string $clientKey;
    private ?string $clientSecret;
    private ?string $accessToken;
    private ?string $openId;

    public function __construct()
    {
        $settings = SiteSetting::query()->find(1);
        $this->clientKey = $settings->tiktok_client_key ?? null;
        $this->clientSecret = $settings->tiktok_client_secret ?? null;
        $this->accessToken = $settings->tiktok_access_token ?? null;
        $this->openId = $settings->tiktok_open_id ?? null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientKey)
            && !empty($this->clientSecret)
            && !empty($this->accessToken);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['connected' => false, 'error' => 'TikTok credentials not configured.'];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => ['display_name', 'bio_description', 'follower_count', 'avatar_url'],
                ]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? [];
                return [
                    'connected' => true,
                    'display_name' => $data['display_name'] ?? null,
                    'follower_count' => $data['follower_count'] ?? 0,
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

    public function fetchVideos(int $maxResults = 20): array
    {
        if (!$this->isConfigured()) {
            return ['error' => 'TikTok not configured', 'status' => 400, 'imported' => 0];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post('https://open.tiktokapis.com/v2/video/list/', [
                    'max_count' => min($maxResults, 20),
                    'fields' => [
                        'id', 'title', 'video_description', 'create_time',
                        'cover_image_url', 'share_url', 'embed_link',
                        'duration', 'like_count', 'comment_count',
                        'share_count', 'view_count',
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('TikTok fetch failed', ['response' => $response->json()]);
                return [
                    'error' => 'TikTok API error: ' . ($response->json()['error']['message'] ?? 'Unknown'),
                    'status' => 502,
                    'imported' => 0,
                ];
            }

            $videos = $response->json()['data']['videos'] ?? [];
            $imported = 0;

            foreach ($videos as $video) {
                $exists = SocialClip::where('platform', 'tiktok')
                    ->where('external_clip_id', $video['id'])
                    ->exists();

                if ($exists) continue;

                SocialClip::create([
                    'platform' => 'tiktok',
                    'external_clip_id' => $video['id'],
                    'title' => $video['title'] ?? 'TikTok Video',
                    'caption' => $video['video_description'] ?? null,
                    'thumbnail_url' => $video['cover_image_url'] ?? null,
                    'clip_url' => $video['share_url'] ?? null,
                    'embed_url' => $video['embed_link'] ?? null,
                    'duration_seconds' => $video['duration'] ?? null,
                    'fetched_at' => now(),
                    'published_at' => isset($video['create_time'])
                        ? \Carbon\Carbon::createFromTimestamp($video['create_time'])
                        : null,
                    'platform_metadata' => [
                        'like_count' => $video['like_count'] ?? 0,
                        'comment_count' => $video['comment_count'] ?? 0,
                        'share_count' => $video['share_count'] ?? 0,
                        'view_count' => $video['view_count'] ?? 0,
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
            Log::error('TikTok fetch exception', ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage(), 'status' => 500, 'imported' => 0];
        }
    }
}
