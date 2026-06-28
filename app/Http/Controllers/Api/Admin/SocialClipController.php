<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialClip;
use App\Models\Video;
use App\Support\FacebookReelsService;
use App\Support\TiktokVideoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialClipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SocialClip::query()->with('linkedVideo:id,title,slug,niche,thumbnail_url');

        if ($platform = $request->query('platform')) {
            $query->where('platform', $platform);
        }

        if ($status = $request->query('mapping_status')) {
            $query->where('mapping_status', $status);
        }

        if ($request->query('needs_review') === 'true') {
            $query->whereIn('mapping_status', ['unlinked', 'auto_mapped']);
        }

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        $sort = $request->query('sort', 'fetched_at');
        $direction = $request->query('direction', 'desc');
        $allowedSorts = ['fetched_at', 'published_at', 'title', 'platform', 'mapping_status', 'duration_seconds'];
        if (in_array($sort, $allowedSorts)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $clips = $query->paginate($perPage);

        return response()->json($clips);
    }

    public function show(string $id): JsonResponse
    {
        $clip = SocialClip::with('linkedVideo:id,title,slug,niche,thumbnail_url,duration')->findOrFail($id);
        return response()->json($clip);
    }

    public function link(Request $request, string $id): JsonResponse
    {
        $clip = SocialClip::findOrFail($id);

        $data = $request->validate([
            'linked_video_id' => ['required', 'uuid', 'exists:videos,id'],
            'mapping_status' => ['required', 'in:manually_mapped,confirmed'],
        ]);

        $clip->update([
            'linked_video_id' => $data['linked_video_id'],
            'mapping_status' => $data['mapping_status'],
            'match_confidence' => 100.00,
            'mapped_by' => $request->user()?->id,
            'mapped_at' => now(),
        ]);

        return response()->json($clip->load('linkedVideo:id,title,slug,niche'));
    }

    public function unlink(string $id): JsonResponse
    {
        $clip = SocialClip::findOrFail($id);
        $clip->update([
            'linked_video_id' => null,
            'mapping_status' => 'unlinked',
            'match_confidence' => null,
            'mapped_by' => null,
            'mapped_at' => null,
        ]);

        return response()->json($clip);
    }

    public function confirmAutoMap(string $id): JsonResponse
    {
        $clip = SocialClip::findOrFail($id);
        $clip->update([
            'mapping_status' => 'confirmed',
            'mapped_by' => request()->user()?->id,
            'mapped_at' => now(),
        ]);

        return response()->json($clip->load('linkedVideo:id,title,slug,niche'));
    }

    public function searchVideos(Request $request): JsonResponse
    {
        $search = $request->query('q', '');
        $videos = Video::where('status', 'published')
            ->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->select('id', 'title', 'slug', 'niche', 'thumbnail_url', 'duration', 'published_at')
            ->orderBy('published_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json($videos);
    }

    // Facebook endpoints

    public function facebookStatus(): JsonResponse
    {
        $service = new FacebookReelsService();
        return response()->json($service->testConnection());
    }

    public function facebookFetch(Request $request): JsonResponse
    {
        $maxResults = (int) $request->input('max_results', 20);
        $service = new FacebookReelsService();
        $result = $service->fetchReels(min($maxResults, 50));

        return response()->json($result, (int) ($result['status'] ?? 200));
    }

    public function facebookRefreshToken(): JsonResponse
    {
        $service = new FacebookReelsService();
        $result = $service->refreshLongLivedToken();

        return response()->json($result, (int) ($result['status'] ?? 200));
    }

    // TikTok endpoints

    public function tiktokStatus(): JsonResponse
    {
        $service = new TiktokVideoService();
        return response()->json($service->testConnection());
    }

    public function tiktokFetch(Request $request): JsonResponse
    {
        $maxResults = (int) $request->input('max_results', 20);
        $service = new TiktokVideoService();
        $result = $service->fetchVideos(min($maxResults, 20));

        return response()->json($result, (int) ($result['status'] ?? 200));
    }

    // Bulk actions

    public function bulkAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid', 'exists:social_clips,id'],
            'action' => ['required', 'in:delete,mark_confirmed,mark_unlinked'],
        ]);

        $count = count($data['ids']);

        match ($data['action']) {
            'delete' => SocialClip::whereIn('id', $data['ids'])->delete(),
            'mark_confirmed' => SocialClip::whereIn('id', $data['ids'])->update([
                'mapping_status' => 'confirmed',
                'mapped_by' => $request->user()?->id,
                'mapped_at' => now(),
            ]),
            'mark_unlinked' => SocialClip::whereIn('id', $data['ids'])->update([
                'linked_video_id' => null,
                'mapping_status' => 'unlinked',
                'match_confidence' => null,
            ]),
        };

        return response()->json(['affected' => $count]);
    }

    public function stats(): JsonResponse
    {
        $total = SocialClip::count();
        $byPlatform = SocialClip::selectRaw('platform, count(*) as count')
            ->groupBy('platform')
            ->pluck('count', 'platform');
        $byStatus = SocialClip::selectRaw('mapping_status, count(*) as count')
            ->groupBy('mapping_status')
            ->pluck('count', 'mapping_status');

        return response()->json([
            'total' => $total,
            'by_platform' => $byPlatform,
            'by_status' => $byStatus,
        ]);
    }
}
