<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get related content for a given content item.
     * Uses tag overlap + same niche + recency scoring.
     */
    public function getRelated(string $contentId, int $limit = 6): array
    {
        $source = Video::find($contentId);
        if (!$source) return [];

        $sourceTags = $source->tagRelations()->pluck('slug')->toArray();
        $sourceNiche = $source->niche;

        // Base query: published content, excluding self
        $query = Video::query()
            ->where('id', '!=', $contentId)
            ->where('status', 'published')
            ->with('tagRelations:id,label,slug');

        // Score-based: same niche = 3pts, tag match = 2pts per tag, recent = 1pt
        $results = $query->get()->map(function ($video) use ($sourceTags, $sourceNiche) {
            $score = 0;
            $videoTags = $video->tagRelations->pluck('slug')->toArray();

            // Niche match
            if ($video->niche === $sourceNiche) $score += 3;

            // Tag overlap
            $tagOverlap = count(array_intersect($sourceTags, $videoTags));
            $score += $tagOverlap * 2;

            // Recency bonus (published in last 7 days)
            if ($video->published_at && $video->published_at->gt(now()->subDays(7))) {
                $score += 1;
            }

            // Popularity bonus
            if (($video->views ?? 0) > 1000) $score += 1;

            return [
                'id' => $video->id,
                'slug' => $video->slug,
                'title' => $video->title,
                'thumbnailUrl' => $video->thumbnail_url,
                'niche' => $video->niche,
                'author' => $video->author,
                'publishedAt' => $video->published_at?->toISOString(),
                'views' => $video->views ?? 0,
                'contentType' => $video->content_type ?? 'video',
                'score' => $score,
            ];
        })
        ->sortByDesc('score')
        ->take($limit)
        ->values()
        ->toArray();

        return $results;
    }

    /**
     * Get personalized recommendations for a user based on reading history.
     */
    public function getForUser(string $userId, int $limit = 10): array
    {
        // Get user's reading history niches and content
        $historyNiche = DB::table('user_view_history')
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(50)
            ->pluck('niche')
            ->filter()
            ->toArray();

        $historyContentIds = DB::table('user_view_history')
            ->where('user_id', $userId)
            ->pluck('content_id')
            ->filter()
            ->toArray();

        // Get user's favorited niches
        $favoriteContentIds = DB::table('user_favorites')
            ->where('user_id', $userId)
            ->pluck('content_id')
            ->toArray();

        $allRead = array_unique(array_merge($historyContentIds, $favoriteContentIds));

        // Weight niches by frequency
        $nicheWeights = array_count_values($historyNiche);
        arsort($nicheWeights);

        if (empty($nicheWeights)) {
            // Cold start: return trending
            return $this->getTrending($limit);
        }

        // Get content from user's top niches, excluding already read
        $topNiches = array_slice(array_keys($nicheWeights), 0, 3);

        $results = Video::query()
            ->where('status', 'published')
            ->whereIn('niche', $topNiches)
            ->when(!empty($allRead), fn ($q) => $q->whereNotIn('id', $allRead))
            ->orderByDesc('published_at')
            ->orderByDesc('views')
            ->limit($limit * 2)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'title' => $v->title,
                'thumbnailUrl' => $v->thumbnail_url,
                'niche' => $v->niche,
                'author' => $v->author,
                'publishedAt' => $v->published_at?->toISOString(),
                'views' => $v->views ?? 0,
                'contentType' => $v->content_type ?? 'video',
            ])
            ->shuffle()
            ->take($limit)
            ->values()
            ->toArray();

        return $results;
    }

    /**
     * Get trending content (24-48 hour window).
     */
    public function getTrending(int $limit = 10): array
    {
        return Video::query()
            ->where('status', 'published')
            ->where('published_at', '>=', now()->subHours(48))
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'title' => $v->title,
                'thumbnailUrl' => $v->thumbnail_url,
                'niche' => $v->niche,
                'author' => $v->author,
                'publishedAt' => $v->published_at?->toISOString(),
                'views' => $v->views ?? 0,
                'contentType' => $v->content_type ?? 'video',
            ])
            ->toArray();
    }
}
