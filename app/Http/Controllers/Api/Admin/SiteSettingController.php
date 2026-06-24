<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SiteSettingController extends Controller
{
    private const ALLOWED = [
        'site_name', 'tagline', 'og_image_url',
        'social_youtube_url', 'social_instagram_url', 'social_x_url', 'social_tiktok_url',
        'ga4_id', 'gtm_id', 'adsense_id',
        'maintenance_mode', 'comments_enabled', 'newsletter_enabled',
        'articles_enabled', 'review_workflow', 'autosave_interval',
        'newsletter_popup_enabled', 'newsletter_popup_interval_hours',
        'newsletter_popup_template', 'newsletter_popup_title',
        'newsletter_popup_body', 'newsletter_popup_categories',
        'youtube_api_key', 'youtube_channel_id', 'shorts_autofetch_enabled',
        'shorts_autofetch_interval_hours', 'max_shorts_per_channel', 'auto_publish_fetched',
        'permalink_structure', 'ad_placements',
    ];

    public function show(): JsonResponse
    {
        return response()->json(SiteSetting::firstOrNew(['id' => 1]));
    }

    public function upsert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_name' => ['sometimes', 'string', 'max:255'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'og_image_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'social_youtube_url' => ['sometimes', 'nullable', 'url'],
            'social_instagram_url' => ['sometimes', 'nullable', 'url'],
            'social_x_url' => ['sometimes', 'nullable', 'url'],
            'social_tiktok_url' => ['sometimes', 'nullable', 'url'],
            'ga4_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gtm_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'adsense_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'maintenance_mode' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'newsletter_enabled' => ['sometimes', 'boolean'],
            'articles_enabled' => ['sometimes', 'boolean'],
            'review_workflow' => ['sometimes', 'boolean'],
            'autosave_interval' => ['sometimes', 'integer', 'min:15', 'max:600'],
            'newsletter_popup_enabled' => ['sometimes', 'boolean'],
            'newsletter_popup_interval_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'newsletter_popup_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newsletter_popup_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newsletter_popup_body' => ['sometimes', 'nullable', 'string'],
            'newsletter_popup_categories' => ['sometimes', 'nullable', 'array'],
            'newsletter_popup_categories.*' => ['string', 'max:120'],
            'youtube_api_key' => ['sometimes', 'nullable', 'string'],
            'youtube_channel_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'shorts_autofetch_enabled' => ['sometimes', 'boolean'],
            'shorts_autofetch_interval_hours' => ['sometimes', 'integer', 'min:1', 'max:168'],
            'max_shorts_per_channel' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'auto_publish_fetched' => ['sometimes', 'boolean'],
            'permalink_structure' => ['sometimes', 'in:plain,type-slug,type-date-slug'],
            'ad_placements' => ['sometimes', 'nullable', 'array'],
        ]);

        // Filter to only columns that actually exist (defensive: migrations may be pending)
        $availableFields = array_filter(self::ALLOWED, fn ($field) => Schema::hasColumn('site_settings', $field));
        $payload = array_merge(['id' => 1], array_intersect_key($validated, array_flip($availableFields)));
        $setting = SiteSetting::updateOrCreate(['id' => 1], $payload);

        return response()->json($setting);
    }
}
