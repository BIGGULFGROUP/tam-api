<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tag::orderByDesc('usage_count');
        if ($q = $request->query('q')) {
            $query->where('label', 'like', "%$q%");
        }
        return response()->json($query->get([
            'id',
            'label',
            'slug',
            'usage_count',
            'description',
            'seo_title',
            'seo_description',
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label'       => ['required', 'string', 'max:100'],
            'slug'        => ['sometimes', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $slug = Str::slug($data['slug'] ?? $data['label']);
        $base = $slug;
        $i    = 2;
        while (Tag::where('slug', $slug)->exists()) {
            $slug = "$base-$i";
            $i++;
        }

        $tag = Tag::create(['id' => Str::uuid(), 'label' => $data['label'], 'slug' => $slug, 'description' => $data['description'] ?? null]);
        return response()->json($tag->only(['id', 'label', 'slug', 'usage_count']), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $tag  = Tag::findOrFail($id);
        $data = $request->only(['label', 'slug', 'description', 'seo_title', 'seo_description']);
        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        $tag->update($data);
        return response()->json($tag->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $tag = Tag::findOrFail($id);
        if ($tag->usage_count > 0) {
            return response()->json(['error' => 'Tag is in use and cannot be deleted'], 400);
        }
        $tag->delete();
        return response()->json(null, 204);
    }
}
