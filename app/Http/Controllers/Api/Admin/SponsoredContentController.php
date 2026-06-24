<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SponsoredContentController extends Controller
{
    public function index(): JsonResponse
    {
        $items = DB::table('sponsored_content')
            ->orderByDesc('display_order')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'imageUrl' => $row->image_url,
                'linkUrl' => $row->link_url,
                'niche' => $row->niche,
                'startsAt' => $row->starts_at,
                'endsAt' => $row->ends_at,
                'isActive' => $row->is_active,
                'displayOrder' => $row->display_order,
            ]);

        return response()->json(['sponsored' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'linkUrl' => ['required', 'string', 'max:1000'],
            'niche' => ['nullable', 'string', 'max:60'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'isActive' => ['boolean'],
        ]);

        $id = Str::uuid();
        DB::table('sponsored_content')->insert([
            'id' => $id,
            'title' => trim($data['title']),
            'body' => $data['body'] ?? null,
            'image_url' => $data['imageUrl'] ?? null,
            'link_url' => trim($data['linkUrl']),
            'niche' => $data['niche'] ?? null,
            'starts_at' => $data['startsAt'] ?? null,
            'ends_at' => $data['endsAt'] ?? null,
            'is_active' => $data['isActive'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'success' => true], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:200'],
            'body' => ['nullable', 'string'],
            'imageUrl' => ['nullable', 'string', 'max:500'],
            'linkUrl' => ['sometimes', 'string', 'max:1000'],
            'niche' => ['nullable', 'string', 'max:60'],
            'startsAt' => ['nullable', 'date'],
            'endsAt' => ['nullable', 'date'],
            'isActive' => ['boolean'],
            'displayOrder' => ['integer', 'min:0'],
        ]);

        $updates = [];
        foreach ($data as $key => $value) {
            if ($value !== null || $key === 'body' || $key === 'niche') {
                $dbKey = match ($key) {
                    'imageUrl' => 'image_url',
                    'linkUrl' => 'link_url',
                    'startsAt' => 'starts_at',
                    'endsAt' => 'ends_at',
                    'isActive' => 'is_active',
                    'displayOrder' => 'display_order',
                    default => $key,
                };
                $updates[$dbKey] = $value;
            }
        }
        $updates['updated_at'] = now();

        DB::table('sponsored_content')->where('id', $id)->update($updates);

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        DB::table('sponsored_content')->where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
