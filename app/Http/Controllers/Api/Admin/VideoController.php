<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentRevision;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Video::query()->with('tagRelations:id,label,slug');

        if ($s = $request->query('q')) {
            $query->where(fn ($q) => $q->where('title', 'like', "%$s%")->orWhere('slug', 'like', "%$s%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('content_type')) {
            $query->where('content_type', $type);
        }
        if ($niche = $request->query('niche')) {
            $query->where('niche', $niche);
        }

        $limit  = min(200, max(1, (int) ($request->query('limit', 50))));
        $page   = max(1, (int) ($request->query('page', 1)));
        $result = $query->orderByDesc('created_at')->paginate($limit, ['*'], 'page', $page);

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'        => ['required', 'string', 'max:500'],
            'niche'        => ['required', 'string'],
            'content_type' => ['sometimes', 'in:video,article,short'],
            'youtube_id'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_channel_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_channel_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_channel_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'author'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'created_by'   => ['sometimes', 'nullable', 'uuid', 'exists:admin_profiles,id'],
            'body'         => ['sometimes', 'nullable', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'tags'         => ['sometimes', 'array'],
            'tags.*'       => ['string', 'max:120'],
            'tag_ids'      => ['sometimes', 'array'],
            'tag_ids.*'    => ['uuid', 'exists:tags,id'],
            'duration'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'       => ['sometimes', 'in:draft,pending_review,published,scheduled,archived,rejected,approved,in_review,revision_requested'],
            'is_featured'  => ['sometimes', 'boolean'],
            'is_breaking'  => ['sometimes', 'boolean'],
            'featured_image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'seo_title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'seo_keywords' => ['sometimes', 'array'],
            'seo_keywords.*' => ['string', 'max:120'],
            'og_image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'collaborator_ids' => ['sometimes', 'array'],
            'collaborator_ids.*' => ['uuid', 'exists:admin_profiles,id'],
            'key_takeaways' => ['sometimes', 'nullable', 'array'],
            'key_takeaways.*.id' => ['required_with:key_takeaways', 'string', 'max:120'],
            'key_takeaways.*.title' => ['required_with:key_takeaways', 'string', 'max:255'],
            'key_takeaways.*.selected' => ['required_with:key_takeaways', 'boolean'],
            'word_count'   => ['sometimes', 'integer', 'min:0'],
            'read_time'    => ['sometimes', 'integer', 'min:1'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $data['id']   = Str::uuid();
        $data['slug'] = $this->uniqueSlug($data['title']);

        $video = DB::transaction(function () use ($data, $request) {
            $video = Video::create($data);
            $this->syncTags($video, $request->input('tag_ids', []));
            $this->syncCreatorCounts([$video->created_by]);

            return $video;
        });

        return response()->json($video->fresh(['tagRelations']), 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(Video::findOrFail($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $video = Video::findOrFail($id);
        $originalCreator = $video->created_by;
        $validated = $request->validate([
            'title'        => ['sometimes', 'string', 'max:500'],
            'niche'        => ['sometimes', 'string'],
            'content_type' => ['sometimes', 'in:video,article,short'],
            'youtube_id'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'source_channel_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_channel_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_channel_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'author'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'created_by'   => ['sometimes', 'nullable', 'uuid', 'exists:admin_profiles,id'],
            'body'         => ['sometimes', 'nullable', 'string'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'tags'         => ['sometimes', 'array'],
            'tags.*'       => ['string', 'max:120'],
            'tag_ids'      => ['sometimes', 'array'],
            'tag_ids.*'    => ['uuid', 'exists:tags,id'],
            'duration'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'       => ['sometimes', 'in:draft,pending_review,published,scheduled,archived,rejected,approved,in_review,revision_requested'],
            'is_featured'  => ['sometimes', 'boolean'],
            'is_breaking'  => ['sometimes', 'boolean'],
            'featured_image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'seo_title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'seo_description' => ['sometimes', 'nullable', 'string'],
            'seo_keywords' => ['sometimes', 'array'],
            'seo_keywords.*' => ['string', 'max:120'],
            'og_image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'collaborator_ids' => ['sometimes', 'array'],
            'collaborator_ids.*' => ['uuid', 'exists:admin_profiles,id'],
            'key_takeaways' => ['sometimes', 'nullable', 'array'],
            'key_takeaways.*.id' => ['required_with:key_takeaways', 'string', 'max:120'],
            'key_takeaways.*.title' => ['required_with:key_takeaways', 'string', 'max:255'],
            'key_takeaways.*.selected' => ['required_with:key_takeaways', 'boolean'],
            'word_count'   => ['sometimes', 'integer', 'min:0'],
            'read_time'    => ['sometimes', 'integer', 'min:1'],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ]);

        DB::transaction(function () use ($request, $validated, $video, $id, $originalCreator) {
            $video->update($validated);

            if ($request->has('title')) {
                $video->slug = $this->uniqueSlug($request->string('title')->toString(), $id);
                $video->save();
            }

            if ($request->has('tag_ids')) {
                $this->syncTags($video, $request->input('tag_ids', []));
            }

            $this->syncCreatorCounts([$originalCreator, $video->created_by]);
        });

        return response()->json($video->fresh(['tagRelations']));
    }

    public function destroy(string $id): JsonResponse
    {
        $video = Video::findOrFail($id);

        DB::transaction(function () use ($video) {
            $creatorId = $video->created_by;
            $video->delete();
            $this->syncCreatorCounts([$creatorId]);
        });

        return response()->json(null, 204);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $source    = Video::findOrFail($id);
        $duplicate = $source->replicate(['id', 'slug', 'views']);
        $duplicate->id           = Str::uuid();
        $duplicate->slug         = $this->uniqueSlug($source->slug.'-copy');
        $duplicate->status       = 'draft';
        $duplicate->published_at = null;
        $duplicate->scheduled_at = null;
        $duplicate->views        = 0;
        $duplicate->save();
        $this->syncTags($duplicate, $source->tagRelations()->pluck('id')->all());
        $this->syncCreatorCounts([$duplicate->created_by]);

        return response()->json(['id' => $duplicate->id]);
    }

    public function revisions(string $id): JsonResponse
    {
        $revisions = ContentRevision::where('content_id', $id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'title', 'body', 'word_count', 'saved_by', 'created_at']);

        return response()->json($revisions);
    }

    public function saveRevision(Request $request, string $id): JsonResponse
    {
        Video::findOrFail($id); // ensure content exists

        $revision = ContentRevision::create([
            'id'         => Str::uuid(),
            'content_id' => $id,
            'title'      => $request->input('title', ''),
            'body'       => $request->input('body', ''),
            'word_count' => $request->input('word_count', 0),
            'saved_by'   => $request->input('saved_by'),
        ]);

        return response()->json($revision, 201);
    }

    public function bulk(Request $request): JsonResponse
    {
        $ids    = $request->input('ids', []);
        $action = $request->input('action', '');

        if (empty($ids)) {
            return response()->json(['error' => 'No rows selected'], 400);
        }

        match ($action) {
            'publish' => Video::whereIn('id', $ids)->update(['status' => 'published', 'published_at' => now()]),
            'draft'   => Video::whereIn('id', $ids)->update(['status' => 'draft']),
            'trash'   => Video::whereIn('id', $ids)->update(['status' => 'archived']),
            'niche', 'category' => Video::whereIn('id', $ids)->update(['niche' => $request->input('category', $request->input('niche', ''))]),
            default   => null,
        };

        return response()->json(['ok' => true]);
    }

    // -------------------------------------------------------------------------

    private function uniqueSlug(string $title, ?string $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i    = 2;

        while (Video::where('slug', $slug)->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))->exists()) {
            $slug = "$base-$i";
            $i++;
        }

        return $slug;
    }

    private function syncTags(Video $video, array $tagIds): void
    {
        $video->tagRelations()->sync(array_filter($tagIds));
    }

    private function syncCreatorCounts(array $adminIds): void
    {
        $adminIds = array_values(array_filter(array_unique($adminIds)));

        if ($adminIds === []) {
            return;
        }

        foreach ($adminIds as $adminId) {
            DB::table('admin_profiles')
                ->where('id', $adminId)
                ->update([
                    'article_count' => Video::where('created_by', $adminId)->where('content_type', 'article')->count(),
                    'video_count' => Video::where('created_by', $adminId)->where('content_type', '!=', 'article')->count(),
                ]);
        }
    }
}
