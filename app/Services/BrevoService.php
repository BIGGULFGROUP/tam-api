<?php

namespace App\Services;

use App\Support\PublicUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.brevo.com/v3';
    private ?string $defaultSender;
    private ?string $defaultSenderName;

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key', '');
        $this->defaultSender = config('services.brevo.sender_email', 'noreply@theafricanmail.com');
        $this->defaultSenderName = config('services.brevo.sender_name', 'The African Mail');
    }

    private function client()
    {
        return Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl($this->apiUrl);
    }

    /**
     * Add or update a contact in Brevo.
     */
    public function syncContact(string $email, array $attributes = [], ?array $listIds = null): bool
    {
        if (!$this->apiKey) {
            Log::info('Brevo: sync skipped (no API key)');
            return false;
        }

        try {
            $payload = [
                'email' => $email,
                'attributes' => array_merge([
                    'SOURCE' => 'tam-website',
                ], $attributes),
                'updateEnabled' => true,
            ];

            if ($listIds) {
                $payload['listIds'] = $listIds;
            }

            $response = $this->client()->post('/contacts', $payload);

            if ($response->successful() || $response->status() === 204) {
                Log::info("Brevo: contact synced", ['email' => $email]);
                return true;
            }

            // Contact already exists — update
            if ($response->status() === 400) {
                $response = $this->client()->put("/contacts/{$email}", [
                    'attributes' => $payload['attributes'],
                    'listIds' => $listIds,
                ]);
                return $response->successful();
            }

            Log::warning('Brevo: sync failed', ['email' => $email, 'status' => $response->status()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Brevo: sync error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a transactional email via Brevo.
     */
    public function sendTransactional(int $templateId, string $toEmail, string $toName, array $params = []): bool
    {
        if (!$this->apiKey) {
            Log::info('Brevo: send skipped (no API key)');
            return false;
        }

        try {
            $response = $this->client()->post('/smtp/email', [
                'templateId' => $templateId,
                'to' => [['email' => $toEmail, 'name' => $toName]],
                'params' => $params,
                'sender' => [
                    'email' => $this->defaultSender,
                    'name' => $this->defaultSenderName,
                ],
            ]);

            $ok = $response->successful();
            Log::info('Brevo: email sent', ['to' => $toEmail, 'ok' => $ok]);
            return $ok;
        } catch (\Throwable $e) {
            Log::error('Brevo: send error', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send daily digest to subscribers of specific niches.
     */
    public function sendDailyDigest(array $topStories, array $subscribers, string $niche = ''): int
    {
        $sent = 0;
        $nicheLabel = $niche ?: 'All';

        foreach ($subscribers as $subscriber) {
            $ok = $this->sendTransactional(
                templateId: (int) config('services.brevo.template_daily_digest', 1),
                toEmail: $subscriber['email'],
                toName: $subscriber['name'] ?? 'Reader',
                params: [
                    'NICHE' => $nicheLabel,
                    'DATE' => now()->format('F j, Y'),
                    'STORIES' => $topStories,
                    'UNSUBSCRIBE_URL' => PublicUrl::to('/account/settings'),
                ]
            );
            if ($ok) $sent++;
        }

        Log::info("Brevo: daily digest sent", ['niche' => $nicheLabel, 'sent' => $sent, 'total' => count($subscribers)]);
        return $sent;
    }

    /**
     * Send a campaign to its targeted subscribers.
     */
    public function sendCampaign(array $stories, array $subscribers, string $title, string $body, ?string $bannerUrl = null): int
    {
        $sent = 0;

        foreach ($subscribers as $subscriber) {
            $ok = $this->sendTransactional(
                templateId: (int) config('services.brevo.template_weekly_roundup', 3),
                toEmail: $subscriber['email'],
                toName: $subscriber['name'] ?? 'Reader',
                params: [
                    'TITLE' => $title,
                    'BODY' => $body,
                    'BANNER_URL' => $bannerUrl,
                    'DATE' => now()->format('F j, Y'),
                    'STORIES' => $stories,
                    'UNSUBSCRIBE_URL' => PublicUrl::to('/account/settings'),
                ]
            );
            if ($ok) $sent++;
        }

        Log::info('Brevo: campaign sent', ['title' => $title, 'sent' => $sent, 'total' => count($subscribers)]);
        return $sent;
    }

    /**
     * Send breaking news alert to niche subscribers.
     */
    public function sendBreakingNewsAlert(array $content, array $subscribers): int
    {
        $sent = 0;

        foreach ($subscribers as $subscriber) {
            $ok = $this->sendTransactional(
                templateId: (int) config('services.brevo.template_breaking_news', 2),
                toEmail: $subscriber['email'],
                toName: $subscriber['name'] ?? 'Reader',
                params: [
                    'TITLE' => $content['title'] ?? 'Breaking News',
                    'BODY' => $content['body'] ?? '',
                    'NICHE' => $content['niche'] ?? '',
                    'READ_URL' => ($content['niche'] ?? '') && ($content['slug'] ?? '')
                        ? PublicUrl::to("/{$content['niche']}/{$content['slug']}")
                        : PublicUrl::to('/'),
                    'DATE' => now()->format('F j, Y g:i A'),
                    'UNSUBSCRIBE_URL' => PublicUrl::to('/account/settings'),
                ]
            );
            if ($ok) $sent++;
        }

        Log::info("Brevo: breaking news alert sent", ['sent' => $sent, 'total' => count($subscribers)]);
        return $sent;
    }

    /**
     * Get Brevo account stats.
     */
    public function getAccountStats(): array
    {
        if (!$this->apiKey) {
            return ['error' => 'Brevo not configured'];
        }

        try {
            $response = $this->client()->get('/account');
            return $response->json() ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get contact count.
     */
    public function getContactCount(): int
    {
        if (!$this->apiKey) return 0;

        try {
            $response = $this->client()->get('/contacts', ['limit' => 1]);
            $data = $response->json();
            return $data['count'] ?? 0;
        } catch (\Throwable) {
            return 0;
        }
    }
}
