<?php

namespace App\Support;

use App\Models\AdminProfile;
use App\Models\Category;
use App\Models\SiteSetting;
use App\Models\Video;
use App\Models\YoutubeFetchLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class YoutubeAdminService
{
    public function getApiKeyStatus(): array
    {
        $envKey = trim((string) env('YOUTUBE_API_KEY', ''));
        if ($envKey !== '') {
            return ['key' => $envKey, 'source' => 'env'];
        }

        $settingsKey = trim((string) optional(SiteSetting::query()->find(1))->youtube_api_key);
        if ($settingsKey !== '') {
            return ['key' => $settingsKey, 'source' => 'settings'];
        }

        return ['key' => null, 'source' => 'missing'];
    }

    public function preview(string $rawValue): array
    {
        $youtubeId = $this->extractYoutubeId($rawValue);
        if ($youtubeId === '') {
            return ['error' => 'Invalid YouTube ID or URL', 'status' => 400];
        }

        $apiKeyStatus = $this->getApiKeyStatus();
        if (! $apiKeyStatus['key']) {
            $fallback = $this->fetchOEmbedMetadata($youtubeId);
            if (! $fallback) {
                return ['error' => 'YouTube API key is not configured and fallback metadata lookup failed', 'status' => 500];
            }

            return array_merge($fallback, [
                'warning' => 'Full YouTube metadata is unavailable because no Data API key is configured.',
            ]);
        }

        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'id' => $youtubeId,
            'part' => 'snippet,contentDetails,statistics',
            'key' => $apiKeyStatus['key'],
        ]);

        $payload = $response->json() ?? [];
        if (! $response->successful()) {
            $upstreamMessage = data_get($payload, 'error.message', 'YouTube API request failed');
            $fallback = $this->fetchOEmbedMetadata($youtubeId);
            if ($fallback) {
                return array_merge($fallback, [
                    'warning' => "Basic metadata only. YouTube Data API failed: {$upstreamMessage}",
                ]);
            }

            return ['error' => $upstreamMessage, 'status' => 502];
        }

        $item = data_get($payload, 'items.0');
        if (! is_array($item)) {
            return ['error' => 'Video not found', 'status' => 404];
        }

        return [
            'id' => $youtubeId,
            'title' => (string) data_get($item, 'snippet.title', ''),
            'description' => (string) data_get($item, 'snippet.description', ''),
            'thumbnailUrl' => data_get($item, 'snippet.thumbnails.maxres.url')
                ?: data_get($item, 'snippet.thumbnails.high.url')
                ?: "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg",
            'duration' => $this->formatDuration((string) data_get($item, 'contentDetails.duration', '')),
            'tags' => array_slice(array_values(array_map('strval', data_get($item, 'snippet.tags', []))), 0, 5),
            'channelTitle' => (string) data_get($item, 'snippet.channelTitle', ''),
            'viewCount' => (int) data_get($item, 'statistics.viewCount', 0),
            'metadataMode' => 'full',
        ];
    }

    public function verifyChannel(string $channelId): array
    {
        $channelId = trim($channelId);
        if ($channelId === '') {
            return ['error' => 'channelId is required', 'status' => 400];
        }

        $apiKeyStatus = $this->getApiKeyStatus();
        if (! $apiKeyStatus['key']) {
            return ['error' => 'YouTube API key is missing', 'status' => 500];
        }

        $response = Http::get('https://www.googleapis.com/youtube/v3/channels', [
            'part' => 'snippet,statistics',
            'id' => $channelId,
            'key' => $apiKeyStatus['key'],
        ]);

        $payload = $response->json() ?? [];
        if (! $response->successful()) {
            return ['error' => data_get($payload, 'error.message', 'YouTube API request failed'), 'status' => 502];
        }

        $item = data_get($payload, 'items.0');
        if (! is_array($item)) {
            return ['error' => 'Channel not found', 'status' => 404];
        }

        return [
            'id' => $channelId,
            'title' => (string) data_get($item, 'snippet.title', ''),
            'subscriberCount' => (int) data_get($item, 'statistics.subscriberCount', 0),
        ];
    }

    public function fetchPreviewForCategory(string $categorySlug): array
    {
        $category = Category::query()
            ->select(['slug', 'youtube_channel_id', 'youtube_playlist_id'])
            ->where('slug', $categorySlug)
            ->first();

        if (! $category) {
            return ['error' => 'Category not found', 'status' => 404];
        }

        if (! $category->youtube_channel_id && ! $category->youtube_playlist_id) {
            return ['error' => 'Category has no YouTube channel configured', 'status' => 400];
        }

        $apiKeyStatus = $this->getApiKeyStatus();
        if (! $apiKeyStatus['key']) {
            return ['error' => 'YouTube API key is missing', 'status' => 500];
        }

        $response = Http::get(
            $category->youtube_playlist_id
                ? 'https://www.googleapis.com/youtube/v3/playlistItems'
                : 'https://www.googleapis.com/youtube/v3/search',
            array_filter([
                'part' => 'snippet',
                'maxResults' => 10,
                'key' => $apiKeyStatus['key'],
                'playlistId' => $category->youtube_playlist_id,
                'channelId' => $category->youtube_playlist_id ? null : $category->youtube_channel_id,
                'order' => $category->youtube_playlist_id ? null : 'date',
                'type' => $category->youtube_playlist_id ? null : 'video',
            ], fn ($value) => $value !== null && $value !== '')
        );

        $payload = $response->json() ?? [];
        if (! $response->successful()) {
            return ['error' => data_get($payload, 'error.message', 'YouTube list request failed'), 'status' => 502];
        }

        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $items = collect($rawItems)
            ->map(function ($item) use ($category) {
                $videoId = $category->youtube_playlist_id
                    ? data_get($item, 'snippet.resourceId.videoId')
                    : data_get($item, 'id.videoId');

                return [
                    'videoId' => $videoId,
                    'title' => (string) data_get($item, 'snippet.title', ''),
                    'publishedAt' => data_get($item, 'snippet.publishedAt'),
                    'thumbnailUrl' => data_get($item, 'snippet.thumbnails.high.url')
                        ?: data_get($item, 'snippet.thumbnails.default.url')
                        ?: '',
                ];
            })
            ->filter(fn ($item) => is_string($item['videoId']) && $item['videoId'] !== '')
            ->values();

        $existingIds = Video::query()
            ->whereIn('youtube_id', $items->pluck('videoId')->all())
            ->pluck('youtube_id')
            ->all();

        $newItems = $items
            ->reject(fn ($item) => in_array($item['videoId'], $existingIds, true))
            ->values();

        $detailMap = [];
        if ($newItems->isNotEmpty()) {
            $detailsResponse = Http::get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet,contentDetails,statistics',
                'id' => implode(',', $newItems->pluck('videoId')->all()),
                'key' => $apiKeyStatus['key'],
            ]);

            $detailsPayload = $detailsResponse->json() ?? [];
            if ($detailsResponse->successful() && is_array($detailsPayload['items'] ?? null)) {
                foreach ($detailsPayload['items'] as $item) {
                    $detailMap[(string) ($item['id'] ?? '')] = [
                        'duration' => $this->formatDuration((string) data_get($item, 'contentDetails.duration', '')),
                        'views' => (int) data_get($item, 'statistics.viewCount', 0),
                        'description' => (string) data_get($item, 'snippet.description', ''),
                    ];
                }
            }
        }

        return $newItems
            ->map(fn ($item) => array_merge($item, [
                'duration' => $detailMap[$item['videoId']]['duration'] ?? '',
                'views' => $detailMap[$item['videoId']]['views'] ?? 0,
                'description' => $detailMap[$item['videoId']]['description'] ?? '',
            ]))
            ->all();
    }

    public function getAutofetchStatus(): array
    {
        $settings = $this->getAutofetchSettings();
        $dueCategories = $settings['enabled']
            ? $this->getDueCategories($settings['intervalHours'])
            : collect();

        return [
            'autofetchEnabled' => $settings['enabled'],
            'intervalHours' => $settings['intervalHours'],
            'maxPerChannel' => $settings['maxPerChannel'],
            'dueNow' => $dueCategories->map(fn (Category $category) => [
                'slug' => $category->slug,
                'label' => $category->label,
                'lastFetchedAt' => optional($category->last_fetched_at)?->toISOString(),
            ])->values()->all(),
            'recentLog' => YoutubeFetchLog::query()
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'category_slug', 'status', 'videos_imported', 'videos_found', 'error_message', 'triggered_by', 'created_at']),
        ];
    }

    public function runAutofetch(?string $forceCategorySlug, int $maxResults, ?string $adminId, string $triggeredBy): array
    {
        $settings = $this->getAutofetchSettings();

        if ($forceCategorySlug) {
            return [
                'processed' => [[
                    'slug' => $forceCategorySlug,
                    ...$this->fetchCategoryVideos($forceCategorySlug, $maxResults, null, $adminId, $triggeredBy),
                ]],
            ];
        }

        if (! $settings['enabled'] && $triggeredBy === 'auto') {
            return ['message' => 'Autofetch is disabled', 'processed' => []];
        }

        $dueCategories = $this->getDueCategories($settings['intervalHours']);
        if ($dueCategories->isEmpty()) {
            return ['message' => 'No categories due for fetch', 'processed' => []];
        }

        $results = $dueCategories->map(function (Category $category) use ($maxResults, $adminId, $triggeredBy) {
            return [
                'slug' => $category->slug,
                'label' => $category->label,
                ...$this->fetchCategoryVideos($category->slug, $maxResults, null, $adminId, $triggeredBy),
            ];
        })->values()->all();

        return [
            'processed' => $results,
            'total' => count($results),
            'triggeredBy' => $triggeredBy,
        ];
    }

    public function fetchCategoryVideos(
        string $categorySlug,
        int $maxResults = 10,
        ?string $selectedAuthorId = null,
        ?string $triggeredByAdmin = null,
        string $triggeredBy = 'manual'
    ): array {
        $category = Category::query()
            ->select(['slug', 'youtube_channel_id', 'youtube_playlist_id'])
            ->where('slug', $categorySlug)
            ->first();

        if (! $category) {
            return ['error' => 'Category not found', 'status' => 404];
        }

        if (! $category->youtube_channel_id && ! $category->youtube_playlist_id) {
            return ['error' => 'Category has no YouTube channel configured', 'status' => 400];
        }

        $apiKeyStatus = $this->getApiKeyStatus();
        if (! $apiKeyStatus['key']) {
            $message = 'YouTube API key is missing';
            $this->logFetch([
                'category_slug' => $categorySlug,
                'status' => 'error',
                'error_message' => $message,
                'triggered_by' => $triggeredBy,
                'triggered_by_admin' => $triggeredByAdmin,
            ]);

            return ['error' => $message, 'status' => 500];
        }

        $response = Http::get(
            $category->youtube_playlist_id
                ? 'https://www.googleapis.com/youtube/v3/playlistItems'
                : 'https://www.googleapis.com/youtube/v3/search',
            array_filter([
                'part' => 'snippet',
                'maxResults' => max(1, min($maxResults, 50)),
                'key' => $apiKeyStatus['key'],
                'playlistId' => $category->youtube_playlist_id,
                'channelId' => $category->youtube_playlist_id ? null : $category->youtube_channel_id,
                'order' => $category->youtube_playlist_id ? null : 'date',
                'type' => $category->youtube_playlist_id ? null : 'video',
            ], fn ($value) => $value !== null && $value !== '')
        );

        $payload = $response->json() ?? [];
        if (! $response->successful()) {
            $message = data_get($payload, 'error.message', 'YouTube request failed');
            $this->logFetch([
                'category_slug' => $categorySlug,
                'status' => 'error',
                'error_message' => $message,
                'triggered_by' => $triggeredBy,
                'triggered_by_admin' => $triggeredByAdmin,
            ]);

            return ['error' => $message, 'status' => $response->status() === 403 ? 429 : 502];
        }

        $rawItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $videoIds = collect($rawItems)
            ->map(fn ($item) => $category->youtube_playlist_id
                ? data_get($item, 'snippet.resourceId.videoId')
                : data_get($item, 'id.videoId'))
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->values()
            ->all();

        try {
            $detailsMap = $this->fetchDetailsMap($videoIds, $apiKeyStatus['key']);
        } catch (\Throwable $exception) {
            $message = $exception->getMessage() ?: 'YouTube details request failed';
            $this->logFetch([
                'category_slug' => $categorySlug,
                'status' => 'error',
                'error_message' => $message,
                'triggered_by' => $triggeredBy,
                'triggered_by_admin' => $triggeredByAdmin,
            ]);

            return ['error' => $message, 'status' => 502];
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $channelProfileCache = [];
        $selectedAuthor = $this->getAuthorFromAdmin($selectedAuthorId ?: $triggeredByAdmin);

        foreach ($rawItems as $item) {
            $videoId = $category->youtube_playlist_id
                ? data_get($item, 'snippet.resourceId.videoId')
                : data_get($item, 'id.videoId');

            if (! is_string($videoId) || $videoId === '') {
                continue;
            }

            $details = $detailsMap[$videoId] ?? [];
            $snippet = is_array($details['snippet'] ?? null) ? $details['snippet'] : (is_array($item['snippet'] ?? null) ? $item['snippet'] : []);
            $title = trim((string) ($snippet['title'] ?? 'Untitled')) ?: 'Untitled';
            $description = (string) ($snippet['description'] ?? '');
            $channelTitle = trim((string) ($snippet['channelTitle'] ?? ''));
            $channelId = trim((string) ($snippet['channelId'] ?? ''));
            $tags = collect(is_array($snippet['tags'] ?? null) ? $snippet['tags'] : [])
                ->map(fn ($tag) => trim((string) $tag))
                ->filter()
                ->take(25)
                ->values()
                ->all();

            $thumbnailUrl = data_get($snippet, 'thumbnails.maxres.url')
                ?: data_get($snippet, 'thumbnails.high.url')
                ?: data_get($snippet, 'thumbnails.medium.url')
                ?: data_get($snippet, 'thumbnails.default.url')
                ?: "https://img.youtube.com/vi/{$videoId}/hqdefault.jpg";

            $contentType = $this->resolveImportedContentType($details, $snippet);
            $sourceChannelSlug = $this->slugifyChannelRoute($channelTitle ?: $categorySlug);

            $channelAuthorId = null;
            if ($channelTitle !== '') {
                if (! array_key_exists($channelTitle, $channelProfileCache)) {
                    $channelProfileCache[$channelTitle] = $this->findOrCreateChannelProfile($channelTitle);
                }
                $channelAuthorId = $channelProfileCache[$channelTitle];
            }

            $commonPayload = [
                'content_type' => $contentType,
                'youtube_id' => $videoId,
                'title' => $title,
                'description' => Str::limit($description, 200, ''),
                'body' => $description,
                'thumbnail_url' => $thumbnailUrl,
                'featured_image_url' => $thumbnailUrl,
                'niche' => $categorySlug,
                'author' => $selectedAuthor['displayName'] ?? $channelTitle ?: 'The African Mail',
                'source_channel_id' => $channelId !== '' ? $channelId : null,
                'source_channel_name' => $channelTitle !== '' ? $channelTitle : null,
                'source_channel_slug' => $sourceChannelSlug !== '' ? $sourceChannelSlug : null,
                'published_at' => $snippet['publishedAt'] ?? null,
                'tags' => $tags,
                'duration' => $this->formatDuration((string) data_get($details, 'contentDetails.duration', '')),
                'views' => (int) data_get($details, 'statistics.viewCount', 0),
                'seo_title' => Str::limit($title, 70, ''),
                'seo_description' => $description !== '' ? Str::limit($description, 155, '') : null,
                'seo_keywords' => $tags,
            ];

            $existing = Video::query()
                ->select(['id', 'status'])
                ->where('youtube_id', $videoId)
                ->first();

            if ($existing) {
                $updatePayload = array_merge($commonPayload, [
                    'status' => $existing->status ?: 'draft',
                ]);

                if ($selectedAuthor['id'] ?? null) {
                    $updatePayload['created_by'] = $selectedAuthor['id'];
                } elseif ($channelAuthorId) {
                    $updatePayload['created_by'] = $channelAuthorId;
                }

                $updated += $existing->update($updatePayload) ? 1 : 0;
                if (! $existing->wasChanged()) {
                    $skipped++;
                }
                continue;
            }

            $created = Video::query()->create(array_merge($commonPayload, [
                'slug' => $this->uniqueSlug($title),
                'created_by' => $selectedAuthor['id'] ?? $channelAuthorId ?? $triggeredByAdmin,
                'status' => 'draft',
            ]));

            if ($created) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        $category->forceFill(['last_fetched_at' => now()])->save();

        $this->logFetch([
            'category_slug' => $categorySlug,
            'videos_found' => count($rawItems),
            'videos_imported' => $imported,
            'videos_skipped' => $skipped,
            'status' => 'success',
            'triggered_by' => $triggeredBy,
            'triggered_by_admin' => $triggeredByAdmin,
        ]);

        return [
            'videosFound' => count($rawItems),
            'videosImported' => $imported,
            'videosUpdated' => $updated,
            'videosSkipped' => $skipped,
        ];
    }

    private function getAutofetchSettings(): array
    {
        $settings = SiteSetting::query()->find(1);

        return [
            'enabled' => (bool) ($settings->shorts_autofetch_enabled ?? false),
            'intervalHours' => max(1, (int) ($settings->shorts_autofetch_interval_hours ?? 6)),
            'maxPerChannel' => max(1, (int) ($settings->max_shorts_per_channel ?? 5)),
        ];
    }

    private function getDueCategories(int $intervalHours)
    {
        $cutoff = now()->subHours($intervalHours);

        return Category::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('auto_fetch_enabled')->orWhere('auto_fetch_enabled', true);
            })
            ->where(function ($query) {
                $query->whereNotNull('youtube_channel_id')->where('youtube_channel_id', '!=', '')
                    ->orWhere(function ($nested) {
                        $nested->whereNotNull('youtube_playlist_id')->where('youtube_playlist_id', '!=', '');
                    });
            })
            ->get()
            ->filter(function (Category $category) use ($cutoff) {
                return ! $category->last_fetched_at || $category->last_fetched_at->lt($cutoff);
            })
            ->values();
    }

    private function fetchDetailsMap(array $videoIds, string $apiKey): array
    {
        if ($videoIds === []) {
            return [];
        }

        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet,contentDetails,statistics',
            'id' => implode(',', $videoIds),
            'maxResults' => count($videoIds),
            'key' => $apiKey,
        ]);

        $payload = $response->json() ?? [];
        if (! $response->successful()) {
            throw new \RuntimeException((string) data_get($payload, 'error.message', 'YouTube details request failed'));
        }

        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $map = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                $map[$id] = $item;
            }
        }

        return $map;
    }

    private function getAuthorFromAdmin(?string $adminId): ?array
    {
        if (! $adminId) {
            return null;
        }

        $admin = AdminProfile::query()
            ->select(['id', 'display_name', 'full_name'])
            ->find($adminId);

        if (! $admin?->id) {
            return null;
        }

        $displayName = trim((string) ($admin->display_name ?: $admin->full_name));
        if ($displayName === '') {
            return null;
        }

        return ['id' => $admin->id, 'displayName' => $displayName];
    }

    private function findOrCreateChannelProfile(string $channelTitle): ?string
    {
        $channelTitle = trim($channelTitle);
        if ($channelTitle === '') {
            return null;
        }

        $existing = AdminProfile::query()
            ->whereRaw('LOWER(display_name) = ?', [Str::lower($channelTitle)])
            ->first();

        if ($existing?->id) {
            return $existing->id;
        }

        $email = sprintf('yt.%s.%s@channel.theafricanmail.com', $this->slugifyChannel($channelTitle), now()->timestamp);
        $profile = AdminProfile::query()->create([
            'email' => $email,
            'password' => Str::random(40),
            'full_name' => $channelTitle,
            'display_name' => $channelTitle,
            'username' => $this->uniqueUsername($channelTitle),
            'role' => 'contributor',
            'is_active' => true,
            'is_public' => true,
        ]);

        return $profile->id;
    }

    private function uniqueUsername(string $channelTitle): string
    {
        $base = str_replace('.', '_', $this->slugifyChannel($channelTitle));
        $base = $base !== '' ? Str::limit($base, 40, '') : 'channel';
        $candidate = $base;
        $suffix = 2;

        while (AdminProfile::query()->where('username', $candidate)->exists()) {
            $suffixText = (string) $suffix;
            $candidate = Str::limit($base, max(1, 40 - strlen($suffixText) - 1), '').'_'.$suffixText;
            $suffix++;
        }

        return $candidate;
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $candidate = $base !== '' ? $base : 'video-'.now()->timestamp;
        $suffix = 2;

        while (Video::query()->where('slug', $candidate)->exists()) {
            $candidate = ($base !== '' ? $base : 'video').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function resolveImportedContentType(array $details = [], array $snippet = []): string
    {
        $durationSeconds = $this->parseDurationSeconds((string) data_get($details, 'contentDetails.duration', ''));
        if ($durationSeconds > 0) {
            return $durationSeconds <= 60 ? 'short' : 'video';
        }

        $shortSignals = collect([
            (string) ($snippet['title'] ?? ''),
            (string) ($snippet['description'] ?? ''),
            ...collect(is_array($snippet['tags'] ?? null) ? $snippet['tags'] : [])->map(fn ($tag) => (string) $tag)->all(),
        ])->implode(' ');

        return preg_match('/(^|\s)#?shorts?(\s|$)/i', $shortSignals) ? 'short' : 'video';
    }

    private function parseDurationSeconds(string $isoDuration): int
    {
        if ($isoDuration === '' || ! preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $isoDuration, $matches)) {
            return 0;
        }

        return ((int) ($matches[1] ?? 0) * 3600)
            + ((int) ($matches[2] ?? 0) * 60)
            + (int) ($matches[3] ?? 0);
    }

    private function formatDuration(string $isoDuration): string
    {
        if ($isoDuration === '' || ! preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $isoDuration, $matches)) {
            return '';
        }

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    private function extractYoutubeId(string $value): string
    {
        $clean = trim($value);
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $clean)) {
            return $clean;
        }

        try {
            $url = new \Illuminate\Support\Uri($clean);
            $host = $url->host();
            if (str_contains($host, 'youtu.be')) {
                $candidate = collect(explode('/', trim($url->path(), '/')))->filter()->first() ?? '';
                if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $candidate)) {
                    return $candidate;
                }
            }

            if (str_contains($host, 'youtube.com')) {
                $fromQuery = $url->query()->get('v', '');
                if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $fromQuery)) {
                    return $fromQuery;
                }

                $segments = collect(explode('/', trim($url->path(), '/')))->filter()->values();
                $last = (string) $segments->last('');
                if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $last)) {
                    return $last;
                }
            }
        } catch (\Throwable) {
            // Fall back to regex extraction below.
        }

        preg_match('/([a-zA-Z0-9_-]{11})/', $clean, $matches);

        return (string) ($matches[1] ?? '');
    }

    private function fetchOEmbedMetadata(string $youtubeId): ?array
    {
        $response = Http::get('https://www.youtube.com/oembed', [
            'url' => "https://www.youtube.com/watch?v={$youtubeId}",
            'format' => 'json',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        return [
            'id' => $youtubeId,
            'title' => (string) ($payload['title'] ?? ''),
            'description' => '',
            'thumbnailUrl' => (string) ($payload['thumbnail_url'] ?? "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg"),
            'duration' => '',
            'tags' => [],
            'channelTitle' => (string) ($payload['author_name'] ?? ''),
            'viewCount' => 0,
            'metadataMode' => 'basic_fallback',
        ];
    }

    private function slugifyChannel(string $input): string
    {
        return Str::of($input)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->substr(0, 40)
            ->value();
    }

    private function slugifyChannelRoute(string $input): string
    {
        return Str::slug($input, '-');
    }

    private function logFetch(array $payload): void
    {
        YoutubeFetchLog::query()->create($payload);
    }
}
