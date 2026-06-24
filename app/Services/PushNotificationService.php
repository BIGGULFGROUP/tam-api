<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private string $expoPushApi = 'https://exp.host/--/api/v2/push/send';
    private ?string $webPushSubject = null;
    private ?string $webPushPublicKey = null;
    private ?string $webPushPrivateKey = null;

    public function __construct()
    {
        $this->webPushSubject = config('services.webpush.subject');
        $this->webPushPublicKey = config('services.webpush.public_key');
        $this->webPushPrivateKey = config('services.webpush.private_key');
    }

    /**
     * Send push notification to all registered devices for a breaking story.
     */
    public function sendBreakingNews(array $content): array
    {
        $results = [
            'expo' => $this->sendExpoPush($content),
            'web' => $this->sendWebPush($content),
            'total_devices' => 0,
        ];

        return $results;
    }

    /**
     * Send via Expo Push API to registered mobile devices.
     */
    private function sendExpoPush(array $content): array
    {
        $tokens = DB::table('push_tokens')
            ->where('is_active', true)
            ->where('platform', 'expo')
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            return ['sent' => 0, 'tokens' => 0];
        }

        $messages = [];
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'title' => $content['title'] ?? 'Breaking News',
                'body' => $this->truncateBody($content['body'] ?? $content['title'] ?? ''),
                'data' => [
                    'type' => 'breaking-news',
                    'niche' => $content['niche'] ?? '',
                    'slug' => $content['slug'] ?? '',
                    'contentId' => $content['id'] ?? '',
                    'url' => ($content['niche'] ?? '') ? "/{$content['niche']}/{$content['slug']}" : '/',
                ],
                'categoryId' => 'breaking-news',
                'sound' => 'default',
                'priority' => 'high',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate',
                ])
                ->post($this->expoPushApi, $messages);

            $data = $response->json();
            $sent = 0;

            if (isset($data['data'])) {
                foreach ($data['data'] as $i => $receipt) {
                    if ($receipt['status'] === 'ok') {
                        $sent++;
                    } elseif (isset($receipt['details']['error']) && $receipt['details']['error'] === 'DeviceNotRegistered') {
                        // Deactivate invalid tokens
                        if (isset($tokens[$i])) {
                            DB::table('push_tokens')->where('token', $tokens[$i])->update(['is_active' => false]);
                        }
                    }
                }
            }

            Log::info('Expo push sent', ['sent' => $sent, 'total' => count($tokens)]);
            return ['sent' => $sent, 'tokens' => count($tokens)];
        } catch (\Throwable $e) {
            Log::error('Expo push failed', ['error' => $e->getMessage()]);
            return ['sent' => 0, 'tokens' => count($tokens), 'error' => $e->getMessage()];
        }
    }

    /**
     * Send web push notifications to subscribed browsers.
     */
    private function sendWebPush(array $content): array
    {
        if (!$this->webPushPrivateKey || !$this->webPushPublicKey) {
            return ['sent' => 0, 'reason' => 'webpush_not_configured'];
        }

        $subscriptions = DB::table('web_push_subscriptions')
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'subscriptions' => 0];
        }

        // Web Push requires minishlink/web-push or manual implementation
        // For now, log and return — full web push implementation needs the PHP library
        Log::info('Web push subscriptions ready', ['count' => $subscriptions->count()]);

        return ['sent' => 0, 'subscriptions' => $subscriptions->count(), 'note' => 'webpush_library_pending'];
    }

    private function truncateBody(string $text, int $maxLen = 160): string
    {
        if (mb_strlen($text) <= $maxLen) {
            return $text;
        }
        return mb_substr($text, 0, $maxLen - 3) . '...';
    }
}
