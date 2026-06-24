<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateLinkController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('affiliate_links')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => array_merge((array) $row, [
                'clickCount' => DB::table('affiliate_clicks')
                    ->where('affiliate_link_id', $row->id)
                    ->count(),
            ]));

        return response()->json(['affiliates' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:affiliate_links,slug'],
            'productName' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'string', 'max:1000'],
            'niche' => ['nullable', 'string', 'max:60'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'isActive' => ['boolean'],
        ]);

        $id = Str::uuid();
        DB::table('affiliate_links')->insert([
            'id' => $id,
            'slug' => Str::slug(trim($data['slug'])),
            'product_name' => trim($data['productName']),
            'description' => $data['description'] ?? null,
            'url' => trim($data['url']),
            'niche' => $data['niche'] ?? null,
            'image_url' => $data['imageUrl'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'success' => true], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'max:100'],
            'productName' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'url' => ['sometimes', 'string', 'max:1000'],
            'niche' => ['nullable', 'string', 'max:60'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'isActive' => ['boolean'],
        ]);

        $updates = [];
        foreach ($data as $key => $value) {
            $dbKey = match ($key) {
                'productName' => 'product_name',
                'imageUrl' => 'image_url',
                'isActive' => 'is_active',
                default => $key,
            };
            $updates[$dbKey] = $value;
        }
        if (isset($updates['slug'])) $updates['slug'] = Str::slug($updates['slug']);
        $updates['updated_at'] = now();

        DB::table('affiliate_links')->where('id', $id)->update($updates);

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('affiliate_clicks')->where('affiliate_link_id', $id)->delete();
        DB::table('affiliate_links')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function recordClick(Request $request, string $slug): JsonResponse
    {
        $link = DB::table('affiliate_links')->where('slug', $slug)->where('is_active', true)->first();
        if (!$link) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::table('affiliate_clicks')->insert([
            'id' => Str::uuid(),
            'affiliate_link_id' => $link->id,
            'content_id' => $request->input('contentId'),
            'ip_address' => $request->header('x-forwarded-for', $request->ip()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['url' => $link->url]);
    }
}
