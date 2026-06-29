<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\DB;

class YoutubeAnalyticsService
{
    /**
     * Aggregate YouTube analytics from locally imported video data.
     *
     * @return array{
     *   overview: array{totalVideos: int, totalShorts: int, totalViews: int, totalChannels: int},
     *   topChannels: array<int, array{name: string, slug: string, videos: int, views: int, subscribers: int|null}>,
     *   topVideos: array<int, array{id: string, title: string, views: int, niche: string, channelName: string, duration: string}>,
     *   byNiche: array<int, array{niche: string, videos: int, views: int}>
     * }
     */
    public function aggregate(): array
    {
        $baseQuery = Video::query()->whereNotNull('youtube_id')->where('youtube_id', '!=', '');

        // Overview
        $totalVideos = (clone $baseQuery)->where('content_type', 'video')->count();
        $totalShorts = (clone $baseQuery)->where('content_type', 'short')->count();
        $totalViews  = (int) (clone $baseQuery)->sum('views');
        $totalChannels = (clone $baseQuery)
            ->whereNotNull('source_channel_name')
            ->distinct('source_channel_name')
            ->count('source_channel_name');

        // Top channels by content count + views
        $topChannels = (clone $baseQuery)
            ->whereNotNull('source_channel_name')
            ->selectRaw(
                "source_channel_name as name,
                 COALESCE(source_channel_slug, '') as slug,
                 count(*) as videos,
                 sum(views) as total_views"
            )
            ->groupBy('source_channel_name', 'source_channel_slug')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'        => $r->name,
                'slug'        => $r->slug ?: '',
                'videos'      => (int) $r->videos,
                'views'       => (int) $r->total_views,
                'subscribers' => null, // needs live YouTube API call
            ])
            ->values()
            ->toArray();

        // Top YouTube videos by views
        $topVideos = (clone $baseQuery)
            ->select([
                'id', 'title', 'views', 'niche',
                'source_channel_name', 'duration',
            ])
            ->orderByDesc('views')
            ->limit(10)
            ->get()
            ->map(fn ($v) => [
                'id'          => $v->id,
                'title'       => $v->title,
                'views'       => (int) $v->views,
                'niche'       => $v->niche,
                'channelName' => $v->source_channel_name ?? 'Unknown',
                'duration'    => $v->duration ?? '',
            ])
            ->values()
            ->toArray();

        // By niche breakdown
        $byNiche = (clone $baseQuery)
            ->selectRaw('niche, count(*) as videos, sum(views) as total_views')
            ->whereNotNull('niche')
            ->groupBy('niche')
            ->orderByDesc('total_views')
            ->get()
            ->map(fn ($r) => [
                'niche'  => $r->niche,
                'videos' => (int) $r->videos,
                'views'  => (int) $r->total_views,
            ])
            ->values()
            ->toArray();

        return [
            'overview'    => [
                'totalVideos'   => $totalVideos,
                'totalShorts'   => $totalShorts,
                'totalViews'    => $totalViews,
                'totalChannels' => $totalChannels,
            ],
            'topChannels' => $topChannels,
            'topVideos'   => $topVideos,
            'byNiche'     => $byNiche,
        ];
    }
}
