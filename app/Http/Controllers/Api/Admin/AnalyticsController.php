<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\UserReadEvent;
use App\Models\Video;
use App\Services\YoutubeAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days      = min(365, max(1, (int) $request->query('days', 30)));
        $since     = now()->subDays($days);

        $videos    = Video::orderByDesc('views')->limit(150)->get(['id', 'title', 'slug', 'niche', 'views', 'status', 'updated_at']);
        $totalViews = $videos->sum('views');
        $published  = $videos->where('status', 'published')->count();
        $drafts     = $videos->where('status', 'draft')->count();

        $countryConfig = config('countries');
        $countryRows = UserReadEvent::query()
            ->where('viewed_at', '>=', $since)
            ->whereNotNull('country_code')
            ->whereNotNull('country_name')
            ->selectRaw('country_code, country_name, count(*) as views')
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('views')
            ->get();

        $countries = $countryRows
            ->map(function ($row) use ($countryConfig) {
                $code = strtoupper($row->country_code);
                $meta = $countryConfig[$code] ?? null;
                return [
                    'code'   => $code,
                    'name'   => $row->country_name,
                    'coords' => $meta['coords'] ?? [0, 0],
                    'views'  => (int) $row->views,
                ];
            })
            ->filter(fn ($c) => isset($countryConfig[$c['code']]))
            ->values();

        // Page view stats
        $totalPageViews = PageView::query()->where('created_at', '>=', $since)->count();

        $dailyViews = PageView::query()
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as date, count(*) as count")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->date, 'count' => (int) $r->count])
            ->values();

        $topPages = PageView::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('path, count(*) as views')
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views])
            ->values();

        $deviceBreakdown = PageView::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('device_type, count(*) as count')
            ->groupBy('device_type')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->device_type => (int) $r->count]);

        $sourceBreakdown = PageView::query()
            ->where('created_at', '>=', $since)
            ->selectRaw(
                "CASE WHEN referrer IS NULL OR referrer = '' THEN 'direct' " .
                "WHEN referrer LIKE '%google.%' OR referrer LIKE '%bing.%' OR referrer LIKE '%yahoo.%' OR referrer LIKE '%duckduckgo.%' THEN 'search' " .
                "WHEN referrer LIKE '%facebook.%' OR referrer LIKE '%twitter.%' OR referrer LIKE '%x.com%' OR referrer LIKE '%instagram.%' OR referrer LIKE '%linkedin.%' OR referrer LIKE '%reddit.%' OR referrer LIKE '%tiktok.%' OR referrer LIKE '%youtube.%' THEN 'social' " .
                "ELSE 'other' END as source, count(*) as count"
            )
            ->groupBy('source')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->source => (int) $r->count]);

        $deviceData = [
            'desktop' => $deviceBreakdown['desktop'] ?? 0,
            'mobile'  => $deviceBreakdown['mobile'] ?? 0,
            'tablet'  => $deviceBreakdown['tablet'] ?? 0,
        ];

        $sourceData = [
            'direct' => $sourceBreakdown['direct'] ?? 0,
            'search' => $sourceBreakdown['search'] ?? 0,
            'social' => $sourceBreakdown['social'] ?? 0,
            'other'  => $sourceBreakdown['other'] ?? 0,
        ];

        $youtube = app(YoutubeAnalyticsService::class)->aggregate();

        return response()->json([
            'provider'    => 'internal',
            'generatedAt' => now()->toISOString(),
            'overview'    => [
                'totalViews'     => $totalViews,
                'totalPosts'     => $videos->count(),
                'publishedPosts' => $published,
                'draftPosts'     => $drafts,
                'pageViews'      => $totalPageViews,
            ],
            'countries' => $countries,
            'topContent' => $videos->take(8)->map(fn ($v) => [
                'id'        => $v->id,
                'title'     => $v->title,
                'slug'      => $v->slug,
                'niche'     => $v->niche,
                'views'     => (int) $v->views,
                'status'    => $v->status,
                'updatedAt' => $v->updated_at,
            ]),
            'pageViews' => [
                'total'    => $totalPageViews,
                'daily'    => $dailyViews,
                'topPages' => $topPages,
                'devices'  => $deviceData,
                'sources'  => $sourceData,
            ],
            'youtube' => $youtube,
        ]);
    }

    public function myContent(Request $request): JsonResponse
    {
        $profile = $request->user();
        $videos  = Video::where('created_by', $profile->id)
            ->orderByDesc('created_at')
            ->get([
                'id',
                'title',
                'slug',
                'niche',
                'views',
                'status',
                'content_type',
                'published_at',
                'tags',
                'updated_at',
            ]);

        return response()->json(['content' => $videos]);
    }
}
