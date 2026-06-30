<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Support\PublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $campaigns = NewsletterCampaign::query()->orderByDesc('created_at')->get();

        $data = $campaigns->map(fn (NewsletterCampaign $c) => $this->present($c));

        return response()->json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'banner_url' => ['nullable', 'url', 'max:1000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'fetch_interval_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $campaign = NewsletterCampaign::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'banner_url' => $data['banner_url'] ?? null,
            'categories' => $data['categories'] ?? [],
            'fetch_interval_hours' => $data['fetch_interval_hours'] ?? 24,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($this->present($campaign), 201);
    }

    public function update(Request $request): JsonResponse
    {
        $id = $request->query('id') ?? $request->input('id');
        $campaign = NewsletterCampaign::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'banner_url' => ['nullable', 'url', 'max:1000'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'fetch_interval_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $campaign->update($data);

        return response()->json($this->present($campaign->fresh()));
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->query('id');
        NewsletterCampaign::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Present the campaign along with the exact public link it will send —
     * lets an editor confirm before activating that links resolve to the
     * real site, not the API host.
     */
    private function present(NewsletterCampaign $campaign): array
    {
        return array_merge($campaign->toArray(), [
            'analytics' => $campaign->analytics(),
            'preview_link' => PublicUrl::to('/'),
            'unsubscribe_link_preview' => PublicUrl::to('/account/settings'),
        ]);
    }
}
