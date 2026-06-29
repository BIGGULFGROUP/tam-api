<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\NewsletterPopupEvent;
use App\Models\NewsletterPopupTemplate;
use App\Models\NewsletterSubscriber;
use App\Models\UserReadEvent;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $subscribers = NewsletterSubscriber::where('is_active', true)->count();
        $comments    = Comment::count();

        $popupEvents = NewsletterPopupEvent::where('created_at', '>=', $since)
            ->get(['event_type', 'template_key', 'category_slug']);

        $popupOverview = ['impressions' => 0, 'closes' => 0, 'submits' => 0];
        $templateMap   = [];
        $categoryMap   = [];

        foreach ($popupEvents as $e) {
            $et = $e->event_type;
            if ($et === 'impression') $popupOverview['impressions']++;
            if ($et === 'close')      $popupOverview['closes']++;
            if ($et === 'submit')     $popupOverview['submits']++;

            $tk = $e->template_key ?? 'unknown';
            $templateMap[$tk] ??= ['key' => $tk, 'name' => $tk, 'impressions' => 0, 'closes' => 0, 'submits' => 0];
            if ($et === 'impression') $templateMap[$tk]['impressions']++;
            if ($et === 'close')      $templateMap[$tk]['closes']++;
            if ($et === 'submit')     $templateMap[$tk]['submits']++;

            $cat = $e->category_slug ?? 'global';
            $categoryMap[$cat] ??= ['category' => $cat, 'impressions' => 0, 'closes' => 0, 'submits' => 0];
            if ($et === 'impression') $categoryMap[$cat]['impressions']++;
            if ($et === 'close')      $categoryMap[$cat]['closes']++;
            if ($et === 'submit')     $categoryMap[$cat]['submits']++;
        }

        $convRate = fn ($s, $i) => $i ? round($s / $i * 100, 2) : 0;

        $templatePerf = collect($templateMap)
            ->map(fn ($t) => array_merge($t, ['conversionRate' => $convRate($t['submits'], $t['impressions'])]))
            ->sortByDesc('submits')->take(8)->values();

        $byCategory = collect($categoryMap)
            ->map(fn ($c) => array_merge($c, ['conversionRate' => $convRate($c['submits'], $c['impressions'])]))
            ->sortByDesc('submits')->take(8)->values();

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

        return response()->json([
            'provider'    => 'internal',
            'generatedAt' => now()->toISOString(),
            'overview'    => [
                'totalViews'     => $totalViews,
                'totalPosts'     => $videos->count(),
                'publishedPosts' => $published,
                'draftPosts'     => $drafts,
                'subscribers'    => $subscribers,
                'comments'       => $comments,
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
            'popup' => array_merge($popupOverview, [
                'days'                => $days,
                'conversionRate'      => $convRate($popupOverview['submits'], $popupOverview['impressions']),
                'templatePerformance' => $templatePerf,
                'byCategory'          => $byCategory,
            ]),
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
