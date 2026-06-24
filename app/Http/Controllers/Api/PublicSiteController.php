<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Models\Category;
use App\Models\Comment;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterPopupEvent;
use App\Models\NewsletterPopupTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'recaptcha_token' => [new \App\Rules\RecaptchaV3()],
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
            'recaptcha_token' => [new \App\Rules\RecaptchaV3()],
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

    public function content(Request $request): JsonResponse
    {
        $query = Video::query();
        $status = $request->query('status', 'published');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $this->applyPublicContentFilters($query, $request);

        if ($request->boolean('countOnly')) {
            return response()->json(['count' => $query->count()]);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 24)));
        $offset = max(0, (int) $request->query('offset', 0));
        $orderBy = $this->publicContentOrderBy($request->query('orderBy', 'published_at'));
        $orderDir = in_array($request->query('orderDir', 'desc'), ['asc', 'desc'], true)
            ? $request->query('orderDir', 'desc')
            : 'desc';
        $rawLimit = min(240, $limit + 20);
        $excludedIds = array_values(array_filter(explode(',', (string) $request->query('exclude', ''))));

        $rows = $query
            ->orderBy($orderBy, $orderDir)
            ->offset($offset)
            ->limit($rawLimit)
            ->get()
            ->reject(fn (Video $video) => in_array((string) $video->id, $excludedIds, true))
            ->values();

        return response()->json([
            'items' => $rows->take($limit)->map(fn (Video $video) => $this->mapVideoFull($video))->values(),
            'count' => $rows->count(),
            'hasMore' => $rows->count() === $rawLimit,
            'page' => max(1, (int) $request->query('page', 1)),
            'nextOffset' => $offset + $rows->count(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->select([
                'slug', 'label', 'short_label', 'description', 'about', 'accent_color',
                'cover_image_url', 'icon', 'content_count', 'seo_title', 'seo_description',
                'youtube_channel_id', 'youtube_channel_name', 'youtube_playlist_id',
                'featured_publication_slug', 'spotlight_title', 'subscribe_title',
                'subscribe_body', 'newsletter_title', 'newsletter_body',
            ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = min(20, max(1, (int) $request->query('limit', 5)));

        if ($query === '') {
            return response()->json(['suggestions' => []]);
        }

        $suggestions = Video::query()
            ->select('title')
            ->where('status', 'published')
            ->where('title', 'ilike', "%{$query}%")
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->pluck('title')
            ->values();

        return response()->json(['suggestions' => $suggestions]);
    }

    public function tags(Request $request): JsonResponse
    {
        $query = Tag::query();
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($builder) => $builder
                ->where('label', 'ilike', "%{$q}%")
                ->orWhere('slug', 'ilike', "%{$q}%")
            );
        }

        return response()->json($query->orderByDesc('usage_count')->get([
            'id',
            'label',
            'slug',
            'usage_count',
            'description',
            'seo_title',
            'seo_description',
        ]));
    }

    public function tagBySlug(string $slug): JsonResponse
    {
        return response()->json(Tag::query()->where('slug', $slug)->firstOrFail());
    }

    public function relatedTags(string $tagId): JsonResponse
    {
        $contentIds = DB::table('content_tags')
            ->where('tag_id', $tagId)
            ->pluck('content_id');

        if ($contentIds->isEmpty()) {
            return response()->json([]);
        }

        $related = DB::table('content_tags')
            ->select('tag_id', DB::raw('count(*) as count'))
            ->whereIn('content_id', $contentIds)
            ->where('tag_id', '!=', $tagId)
            ->groupBy('tag_id')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        if ($related->isEmpty()) {
            return response()->json([]);
        }

        $tags = Tag::query()
            ->whereIn('id', $related->pluck('tag_id'))
            ->get(['id', 'label', 'slug'])
            ->keyBy('id');

        return response()->json($related->map(fn ($row) => [
            'label' => $tags[$row->tag_id]?->label ?? '',
            'slug' => $tags[$row->tag_id]?->slug ?? '',
            'count' => (int) $row->count,
        ])->values());
    }

    public function authorBySlug(string $slug): JsonResponse
    {
        $author = AdminProfile::query()
            ->select([
                'id', 'display_name', 'username', 'bio', 'avatar_url', 'website_url', 'twitter_url',
                'instagram_url', 'linkedin_url', 'location', 'author_slug', 'article_count', 'video_count',
                'is_public', 'is_active',
            ])
            ->where('author_slug', $slug)
            ->where('is_public', true)
            ->where('is_active', true)
            ->first();

        return response()->json($author ? $this->mapAuthor($author) : null);
    }

    public function authorByName(string $name): JsonResponse
    {
        $author = AdminProfile::query()
            ->select([
                'id', 'display_name', 'username', 'bio', 'avatar_url', 'website_url', 'twitter_url',
                'instagram_url', 'linkedin_url', 'location', 'author_slug', 'article_count', 'video_count',
                'is_public', 'is_active',
            ])
            ->where('display_name', $name)
            ->where('is_public', true)
            ->where('is_active', true)
            ->first();

        return response()->json($author ? $this->mapAuthor($author) : null);
    }

    public function authorById(string $id): JsonResponse
    {
        $author = AdminProfile::query()
            ->select([
                'id', 'display_name', 'username', 'bio', 'avatar_url', 'website_url', 'twitter_url',
                'instagram_url', 'linkedin_url', 'location', 'author_slug', 'article_count', 'video_count',
                'is_public', 'is_active',
            ])
            ->where('id', $id)
            ->where('is_public', true)
            ->where('is_active', true)
            ->first();

        return response()->json($author ? $this->mapAuthor($author) : null);
    }

    public function newsletters(Request $request): JsonResponse
    {
        $niche = trim((string) $request->query('niche', ''));
        $campaigns = NewsletterCampaign::query()
            ->select(['id', 'newsletter_key', 'title', 'body', 'banner_url', 'categories', 'fetch_interval_hours', 'is_active', 'created_at', 'updated_at'])
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get();

        if ($niche !== '') {
            $campaigns = $campaigns->filter(fn (NewsletterCampaign $campaign) => in_array($niche, $campaign->categories ?? [], true));
        }

        $minInterval = $campaigns->reduce(function (?int $min, NewsletterCampaign $campaign) {
            $next = (int) ($campaign->fetch_interval_hours ?? 24);
            return $min === null ? $next : min($min, max(1, $next));
        });

        return response()->json([
            'newsletters' => $campaigns->values(),
            'recommendedPollIntervalHours' => $minInterval ?? 24,
            'recommendedPollIntervalMs' => ($minInterval ?? 24) * 60 * 60 * 1000,
            'generatedAt' => now()->toISOString(),
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

    private function applyPublicContentFilters($query, Request $request): void
    {
        if ($type = $request->query('content_type')) {
            $types = array_values(array_filter(explode(',', (string) $type)));
            if ($types !== []) {
                $query->whereIn('content_type', $types);
            }
        }

        if ($niche = $request->query('niche')) {
            $niches = array_values(array_filter(explode(',', (string) $niche)));
            if ($niches !== []) {
                $query->whereIn('niche', $niches);
            }
        }

        if ($slug = trim((string) $request->query('slug', ''))) {
            $query->where('slug', $slug);
        }

        if ($sourceChannelSlug = trim((string) $request->query('source_channel_slug', ''))) {
            $query->where('source_channel_slug', $sourceChannelSlug);
        }

        if ($tagSlug = trim((string) $request->query('tag_slug', ''))) {
            $query->whereExists(fn ($builder) => $builder
                ->from('content_tags')
                ->join('tags', 'tags.id', '=', 'content_tags.tag_id')
                ->whereColumn('content_tags.content_id', 'videos.id')
                ->where('tags.slug', $tagSlug)
            );
        }

        if ($tagId = trim((string) $request->query('tag_id', ''))) {
            $query->whereExists(fn ($builder) => $builder
                ->from('content_tags')
                ->whereColumn('content_tags.content_id', 'videos.id')
                ->where('content_tags.tag_id', $tagId)
            );
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($builder) => $builder
                ->where('title', 'ilike', "%{$q}%")
                ->orWhere('description', 'ilike', "%{$q}%")
                ->orWhere('author', 'ilike', "%{$q}%")
                ->orWhere('body', 'ilike', "%{$q}%")
            );
        }

        if ($authorId = trim((string) $request->query('author_id', ''))) {
            $query->where('created_by', $authorId);
        }

        if ($author = trim((string) $request->query('author', ''))) {
            $query->where('author', $author);
        }

        if ($ids = trim((string) $request->query('ids', ''))) {
            $query->whereIn('id', array_values(array_filter(explode(',', $ids))));
        }

        if ($featured = $request->query('featured')) {
            $query->where('is_featured', filter_var($featured, FILTER_VALIDATE_BOOLEAN));
        }

        if ($breaking = $request->query('breaking')) {
            $query->where('is_breaking', filter_var($breaking, FILTER_VALIDATE_BOOLEAN));
        }

        if ($gte = trim((string) $request->query('gte', ''))) {
            $query->where('published_at', '>=', $gte);
        }
    }

    private function publicContentOrderBy(string $orderBy): string
    {
        return match ($orderBy) {
            'views', 'title', 'created_at' => $orderBy,
            default => 'published_at',
        };
    }

    private function mapAuthor(AdminProfile $author): array
    {
        return [
            'id' => $author->id,
            'displayName' => $author->display_name,
            'username' => $author->username,
            'bio' => $author->bio,
            'avatarUrl' => $author->avatar_url,
            'websiteUrl' => $author->website_url,
            'twitterUrl' => $author->twitter_url,
            'instagramUrl' => $author->instagram_url,
            'linkedinUrl' => $author->linkedin_url,
            'location' => $author->location,
            'authorSlug' => $author->author_slug,
            'articleCount' => (int) ($author->article_count ?? 0),
            'videoCount' => (int) ($author->video_count ?? 0),
            'isPublic' => (bool) $author->is_public,
        ];
    }

    private function mapVideoFull(Video $video): array
    {
        return [
            'id' => $video->id,
            'slug' => $video->slug,
            'title' => $video->title,
            'description' => $video->description ?? '',
            'body' => $video->body ?? '',
            'youtube_id' => $video->youtube_id ?? '',
            'thumbnail_url' => $video->thumbnail_url ?? '',
            'featured_image_url' => $video->featured_image_url ?? null,
            'niche' => $video->niche,
            'tags' => $video->tags ?? [],
            'author' => $video->author ?? '',
            'author_slug' => Str::slug((string) ($video->author ?? '')),
            'source_channel_id' => $video->source_channel_id,
            'source_channel_name' => $video->source_channel_name,
            'source_channel_slug' => $video->source_channel_slug,
            'created_by' => $video->created_by,
            'collaborator_ids' => $video->collaborator_ids ?? [],
            'published_at' => $video->published_at?->toISOString() ?? '',
            'duration' => $video->duration ?? '',
            'views' => (int) ($video->views ?? 0),
            'is_featured' => (bool) ($video->is_featured ?? false),
            'is_breaking' => (bool) ($video->is_breaking ?? false),
            'seo_title' => $video->seo_title,
            'seo_description' => $video->seo_description,
            'seo_keywords' => $video->seo_keywords ?? [],
            'og_image_url' => $video->og_image_url,
            'content_type' => $video->content_type ?? 'video',
            'word_count' => (int) ($video->word_count ?? 0),
            'read_time' => (int) ($video->read_time ?? 0),
            'status' => $video->status ?? 'published',
            'key_takeaways' => $video->key_takeaways ?? [],
        ];
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
