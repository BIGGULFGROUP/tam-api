<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Comment;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterPopupEvent;
use App\Models\NewsletterPopupTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PublicSiteController extends Controller
{
    public function latestContent(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $limit = min(24, max(4, (int) $request->query('limit', 12)));
        $offset = max(0, (int) $request->query('offset', ($page - 1) * $limit));
        $rawLimit = min(120, $limit + 20);
        $excludedIds = array_values(array_filter(explode(',', (string) $request->query('exclude', ''))));

        $rows = Video::query()
            ->select([
                'id', 'slug', 'title', 'description', 'thumbnail_url', 'featured_image_url',
                'niche', 'author', 'published_at', 'duration', 'views', 'content_type',
                'read_time', 'word_count', 'tags', 'youtube_id',
            ])
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->offset($offset)
            ->limit($rawLimit)
            ->get()
            ->reject(fn (Video $video) => in_array((string) $video->id, $excludedIds, true))
            ->values();

        $paged = $rows->take($limit)->map(fn (Video $video) => $this->mapVideoPreview($video));

        return response()->json([
            'items' => $paged,
            'hasMore' => $rows->count() === $rawLimit,
            'page' => $page,
            'nextOffset' => $offset + $rows->count(),
        ]);
    }

    public function navMenu(): JsonResponse
    {
        $categories = Category::query()
            ->select(['slug', 'label'])
            ->orderBy('label')
            ->get()
            ->map(fn (Category $category) => [
                'slug' => $category->slug,
                'label' => $category->label,
            ]);

        $content = Video::query()
            ->select(['id', 'slug', 'title', 'niche', 'content_type', 'thumbnail_url', 'featured_image_url'])
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit(240)
            ->get();

        $previewsByCategory = [];
        foreach ($content as $row) {
            if (! $row->niche || ! $row->slug || ! $row->title) {
                continue;
            }

            $previewsByCategory[$row->niche] ??= [];
            if (count($previewsByCategory[$row->niche]) >= 3) {
                continue;
            }

            $previewsByCategory[$row->niche][] = [
                'id' => $row->id,
                'slug' => $row->slug,
                'niche' => $row->niche,
                'title' => $row->title,
                'contentType' => $row->content_type ?? 'video',
                'imageUrl' => $this->resolvePrimaryImage($row),
            ];
        }

        return response()->json([
            'categories' => $categories,
            'previewsByCategory' => $previewsByCategory,
        ]);
    }

    public function shorts(Request $request): JsonResponse
    {
        $limit = min(200, max(10, (int) $request->query('limit', 120)));
        $slug = trim((string) $request->query('slug', ''));

        $rows = Video::query()
            ->select([
                'id', 'slug', 'title', 'description', 'thumbnail_url', 'featured_image_url',
                'niche', 'author', 'published_at', 'duration', 'views', 'content_type',
                'read_time', 'word_count', 'tags', 'youtube_id',
            ])
            ->where('status', 'published')
            ->where('content_type', 'short')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();

        $items = $rows->map(fn (Video $video) => $this->mapShort($video))->values();

        if ($slug !== '' && ! $items->contains(fn (array $item) => $item['slug'] === $slug)) {
            $selected = Video::query()
                ->where('status', 'published')
                ->where('content_type', 'short')
                ->where('slug', $slug)
                ->first();

            if ($selected) {
                $items = collect([$this->mapShort($selected)])->concat($items)->values();
            }
        }

        return response()->json(['items' => $items]);
    }

    public function recordView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contentId' => ['required', 'uuid'],
        ]);

        $video = Video::query()->find($data['contentId']);
        if (! $video) {
            return response()->json(['recorded' => false, 'error' => 'Content not found'], 200);
        }

        $video->increment('views');

        return response()->json(['recorded' => true]);
    }

    public function comments(Request $request): JsonResponse
    {
        $contentId = (string) $request->query('video_id', '');
        if ($contentId === '') {
            return response()->json(['error' => 'video_id required'], 400);
        }

        $comments = Comment::query()
            ->select(['id', 'author_name', 'body', 'created_at'])
            ->where('content_id', $contentId)
            ->where('is_approved', true)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['comments' => $comments]);
    }

    public function submitComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'videoId' => ['required', 'uuid'],
            'body' => ['required', 'string', 'max:1000'],
            'authorName' => ['required', 'string', 'max:120'],
            'authorEmail' => ['nullable', 'email'],
        ]);

        Comment::query()->create([
            'content_id' => $data['videoId'],
            'author_name' => trim($data['authorName']),
            'author_email' => $data['authorEmail'] ?? null,
            'body' => trim($data['body']),
            'ip_address' => $request->header('x-forwarded-for', $request->ip()),
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment submitted and awaiting moderation. Thank you!',
        ]);
    }

    public function subscribeNewsletter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:60'],
            'source' => ['nullable', 'string', 'max:60'],
            'popupType' => ['nullable', 'string', 'max:120'],
            'context' => ['nullable', 'array'],
        ]);

        $email = Str::lower(trim($data['email']));
        $categories = array_values(array_unique(array_filter($data['categories'] ?? [])));

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();
        if ($subscriber) {
            $merged = array_values(array_unique(array_merge($subscriber->niches ?? [], $categories)));
            $subscriber->update([
                'name' => $data['name'] ?? $subscriber->name,
                'niches' => $merged,
                'is_active' => true,
                'source' => $data['source'] ?? 'website',
                'popup_type' => $data['popupType'] ?? null,
                'subscription_context' => $data['context'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription preferences updated!',
            ]);
        }

        NewsletterSubscriber::query()->create([
            'email' => $email,
            'name' => $data['name'] ?? null,
            'niches' => $categories,
            'source' => $data['source'] ?? 'website',
            'popup_type' => $data['popupType'] ?? null,
            'subscription_context' => $data['context'] ?? [],
            'is_active' => true,
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscribed successfully!',
        ]);
    }

    public function popupConfig(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $normalizedCategory = is_string($category) && $category !== '' ? $category : null;

        $settings = SiteSetting::query()->find(1);
        $categoryConfig = $normalizedCategory
            ? Category::query()
                ->select(['slug', 'label', 'newsletter_title', 'newsletter_body', 'cover_image_url'])
                ->where('slug', $normalizedCategory)
                ->first()
            : null;

        $campaigns = NewsletterCampaign::query()
            ->select(['newsletter_key', 'title', 'body', 'banner_url', 'categories', 'fetch_interval_hours'])
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();

        $matchedCampaign = null;
        if ($campaigns->isNotEmpty()) {
            if ($normalizedCategory) {
                $matchedCampaign = $campaigns->first(
                    fn (NewsletterCampaign $item) => in_array($normalizedCategory, $item->categories ?? [], true)
                );
            }

            if (! $matchedCampaign) {
                $matchedCampaign = $campaigns->first(
                    fn (NewsletterCampaign $item) => empty($item->categories)
                ) ?? $campaigns->first();
            }
        }

        $templates = NewsletterPopupTemplate::query()
            ->select(['template_key', 'title', 'body', 'interval_hours', 'categories'])
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();

        $template = null;
        if ($templates->isNotEmpty()) {
            if ($normalizedCategory) {
                $template = $templates->first(
                    fn (NewsletterPopupTemplate $item) => in_array($normalizedCategory, $item->categories ?? [], true)
                );
            }

            if (! $template) {
                $template = $templates->firstWhere('template_key', $settings?->newsletter_popup_template)
                    ?? $templates->first();
            }
        }

        $selected = $normalizedCategory
            ? [$normalizedCategory]
            : ($settings?->newsletter_popup_categories ?? []);

        $options = Category::query()
            ->orderBy('label')
            ->get(['slug', 'short_label'])
            ->map(fn (Category $categoryRow) => [
                'slug' => $categoryRow->slug,
                'label' => $categoryRow->short_label,
                'selected' => $selected === [] ? true : in_array($categoryRow->slug, $selected, true),
            ]);

        return response()->json([
            'enabled' => $settings?->newsletter_popup_enabled ?? true,
            'intervalHours' => max(
                1,
                (int) ($matchedCampaign?->fetch_interval_hours
                    ?? $template?->interval_hours
                    ?? $settings?->newsletter_popup_interval_hours
                    ?? 24)
            ),
            'template' => $matchedCampaign
                ? 'campaign:'.$matchedCampaign->newsletter_key
                : ($template?->template_key ?? $settings?->newsletter_popup_template ?? 'category_interest'),
            'title' => $matchedCampaign?->title
                ?? $categoryConfig?->newsletter_title
                ?? $template?->title
                ?? $settings?->newsletter_popup_title
                ?? 'Pick categories you want updates from',
            'body' => $matchedCampaign?->body
                ?? $categoryConfig?->newsletter_body
                ?? $template?->body
                ?? $settings?->newsletter_popup_body
                ?? 'Choose the sections you care about and get only those updates.',
            'imageUrl' => $matchedCampaign?->banner_url ?: $categoryConfig?->cover_image_url,
            'options' => $options,
        ]);
    }

    public function siteSettings(): JsonResponse
    {
        $settings = SiteSetting::query()->find(1);

        return response()->json([
            'adsenseId' => $settings?->adsense_id ?? '',
            'permalinkStructure' => $settings?->permalink_structure ?? 'plain',
            'socialLinks' => [
                'youtubeUrl' => $settings?->social_youtube_url,
                'instagramUrl' => $settings?->social_instagram_url,
                'xUrl' => $settings?->social_x_url,
                'tiktokUrl' => $settings?->social_tiktok_url,
            ],
        ]);
    }

    public function popupEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eventType' => ['required', 'in:impression,close,submit,click'],
            'templateKey' => ['nullable', 'string', 'max:160'],
            'campaignKey' => ['nullable', 'string', 'max:160'],
            'categorySlug' => ['nullable', 'string', 'max:80'],
            'pagePath' => ['nullable', 'string', 'max:2048'],
        ]);

        $templateKey = $data['templateKey'] ?? null;
        if (! $templateKey && ! empty($data['campaignKey'])) {
            $templateKey = 'campaign:'.$data['campaignKey'];
        }

        NewsletterPopupEvent::query()->create([
            'event_type' => $data['eventType'],
            'template_key' => $templateKey,
            'category_slug' => $data['categorySlug'] ?? null,
            'page_path' => $data['pagePath'] ?? null,
            'session_id' => (string) $request->cookie('laravel_session', ''),
        ]);

        return response()->json(['success' => true]);
    }

    private function mapVideoPreview(Video $video): array
    {
        $thumbnailUrl = $video->content_type === 'article'
            ? ($video->featured_image_url ?: $video->thumbnail_url ?: '')
            : ($video->thumbnail_url ?: $video->featured_image_url ?: '');

        return [
            'id' => $video->id,
            'slug' => $video->slug,
            'title' => $video->title,
            'description' => $video->description ?? '',
            'body' => '',
            'youtubeId' => $video->youtube_id ?? '',
            'thumbnailUrl' => $thumbnailUrl,
            'category' => $video->niche,
            'niche' => $video->niche,
            'tags' => $video->tags ?? [],
            'author' => $video->author ?? '',
            'authorSlug' => Str::slug((string) $video->author),
            'publishedAt' => optional($video->published_at)->toISOString() ?? '',
            'duration' => $video->duration ?? '',
            'views' => (int) ($video->views ?? 0),
            'isFeatured' => false,
            'isBreaking' => false,
            'contentType' => $video->content_type ?? 'video',
            'featuredImageUrl' => $video->featured_image_url ?? null,
            'wordCount' => (int) ($video->word_count ?? 0),
            'readTime' => (int) ($video->read_time ?? 0),
            'status' => 'published',
        ];
    }

    private function mapShort(Video $video): array
    {
        $item = $this->mapVideoPreview($video);
        $item['thumbnailUrl'] = $video->thumbnail_url ?: $video->featured_image_url ?: '';
        $item['contentType'] = 'short';

        return $item;
    }

    private function resolvePrimaryImage(Video $video): string
    {
        return $video->content_type === 'article'
            ? ($video->featured_image_url ?: $video->thumbnail_url ?: '')
            : ($video->thumbnail_url ?: $video->featured_image_url ?: '');
    }
}
