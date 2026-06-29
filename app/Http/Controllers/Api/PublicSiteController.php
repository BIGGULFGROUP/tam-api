<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Models\Category;
use App\Models\Comment;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\PageView;
use App\Models\Video;
use App\Services\IpGeolocationService;
use App\Services\UserAgentParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\RecommendationService;

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

        // Record page view with geo + device analytics
        $ip = $request->ip();
        $geo = app(IpGeolocationService::class)->resolve($ip);
        $ua = app(UserAgentParser::class)->parse($request->userAgent());

        PageView::query()->create([
            'path'         => $request->input('path', '/'),
            'referrer'     => $request->input('referrer'),
            'user_agent'   => $request->userAgent(),
            'ip_address'   => $ip,
            'country_code' => $geo['code'],
            'country_name' => $geo['name'],
            'device_type'  => $ua['device_type'],
            'browser'      => $ua['browser'],
            'os'           => $ua['os'],
            'content_id'   => $data['contentId'],
        ]);

        return response()->json(['recorded' => true]);
    }

    public function recordPageView(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path'     => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        $ip = $request->ip();
        $geo = app(IpGeolocationService::class)->resolve($ip);
        $ua = app(UserAgentParser::class)->parse($request->userAgent());

        PageView::query()->create([
            'path'         => $data['path'] ?? '/',
            'referrer'     => $data['referrer'] ?? $request->header('Referer'),
            'user_agent'   => $request->userAgent(),
            'ip_address'   => $ip,
            'country_code' => $geo['code'],
            'country_name' => $geo['name'],
            'device_type'  => $ua['device_type'],
            'browser'      => $ua['browser'],
            'os'           => $ua['os'],
        ]);

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

