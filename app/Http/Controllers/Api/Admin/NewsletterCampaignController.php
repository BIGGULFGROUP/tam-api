<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterPopupEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = NewsletterCampaign::orderByDesc('created_at')->get();

        $keys   = $campaigns->pluck('newsletter_key')->map(fn ($k) => "campaign:$k")->all();
        $events = $keys
            ? NewsletterPopupEvent::whereIn('template_key', $keys)->get(['template_key', 'event_type'])
            : collect();

        $byKey = [];
        foreach ($events as $e) {
            $key = preg_replace('/^campaign:/', '', $e->template_key);
            $byKey[$key] ??= ['impressions' => 0, 'submits' => 0, 'clicks' => 0];
            if ($e->event_type === 'impression') $byKey[$key]['impressions']++;
            if ($e->event_type === 'submit')     $byKey[$key]['submits']++;
            if ($e->event_type === 'click')      $byKey[$key]['clicks']++;
        }

        $result = $campaigns->map(function ($c) use ($byKey) {
            $s = $byKey[$c->newsletter_key] ?? ['impressions' => 0, 'submits' => 0, 'clicks' => 0];
            return array_merge($c->toArray(), ['analytics' => array_merge($s, [
                'conversion_rate' => $s['impressions'] ? round($s['submits'] / $s['impressions'] * 100, 2) : 0,
            ])]);
        });

        return response()->json(['newsletters' => $result]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'                => ['required', 'string'],
            'body'                 => ['required', 'string'],
            'banner_url'           => ['nullable', 'url'],
            'categories'           => ['sometimes', 'array'],
            'fetch_interval_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
        ]);

        $key   = Str::slug($data['title']);
        $norm  = strtolower(trim($data['title']));

        if (NewsletterCampaign::where('normalized_title', $norm)->exists()) {
            return response()->json(['error' => 'A newsletter with this title already exists.'], 409);
        }

        $campaign = NewsletterCampaign::create([
            'id'                   => Str::uuid(),
            'newsletter_key'       => $key,
            'normalized_title'     => $norm,
            'title'                => $data['title'],
            'body'                 => $data['body'],
            'banner_url'           => $data['banner_url'] ?? null,
            'categories'           => $data['categories'] ?? [],
            'fetch_interval_hours' => $data['fetch_interval_hours'] ?? 24,
            'is_active'            => true,
            'created_by'           => $request->user()?->id,
        ]);

        return response()->json(['newsletter' => $campaign]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'uuid'],
            'title' => ['sometimes', 'string'],
            'body' => ['sometimes', 'string'],
            'banner_url' => ['sometimes', 'nullable', 'url'],
            'categories' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'fetch_interval_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
        ]);

        $id       = $data['id'];
        $campaign = NewsletterCampaign::findOrFail($id);
        $campaign->update(array_merge(
            collect($data)->except(['id', 'newsletter_key', 'normalized_title'])->all(),
            isset($data['title'])
                ? [
                    'newsletter_key' => Str::slug($data['title']),
                    'normalized_title' => strtolower(trim($data['title'])),
                ]
                : []
        ));
        return response()->json(['newsletter' => $campaign->fresh()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->query('id');
        NewsletterCampaign::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
