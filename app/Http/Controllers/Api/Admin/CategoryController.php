<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\YoutubeAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')->get();

        // Append real video counts
        $counts = \App\Models\Video::query()
            ->selectRaw('niche, count(*) as total')
            ->groupBy('niche')
            ->pluck('total', 'niche');

        $categories->each(function (Category $category) use ($counts) {
            $category->content_count = (int) ($counts[$category->slug] ?? 0);
        });

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $data     = $request->validate(['slug' => ['required', 'string', 'unique:categories,slug'], 'label' => ['required', 'string']]);
        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json(Category::where('slug', $slug)->firstOrFail());
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $hadChannel = $category->youtube_channel_id || $category->youtube_channel_username;
        $category->update($request->except(['slug', 'id']));
        $category->refresh();
        $hasChannel = $category->youtube_channel_id || $category->youtube_channel_username;

        // Auto-fetch when a YouTube channel is newly connected
        if (! $hadChannel && $hasChannel) {
            try {
                $service = app(YoutubeAdminService::class);
                $service->fetchCategoryVideos(
                    $category->slug,
                    10,
                    $request->user()?->id,
                    $request->user()?->id,
                    'auto'
                );
            } catch (\Throwable $e) {
                // Log but don't block the update — fetch runs best-effort
                \Illuminate\Support\Facades\Log::warning(
                    'Auto-fetch on channel connect failed for category: ' . $category->slug,
                    ['error' => $e->getMessage()]
                );
            }
        }

        return response()->json($category->fresh());
    }
}
