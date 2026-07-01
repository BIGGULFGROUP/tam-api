<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class SiteSettingController extends Controller
{
    private const ALLOWED = [
        'site_name', 'tagline', 'og_image_url',
        'social_youtube_url', 'social_instagram_url', 'social_x_url', 'social_tiktok_url',
        'social_facebook_url', 'social_linkedin_url',
        'ga4_id', 'gtm_id', 'adsense_id',
        'maintenance_mode', 'comments_enabled', 'newsletter_enabled',
        'articles_enabled', 'review_workflow', 'autosave_interval',
        'newsletter_popup_enabled', 'newsletter_popup_interval_hours',
        'newsletter_popup_template', 'newsletter_popup_title',
        'newsletter_popup_body', 'newsletter_popup_categories',
        'youtube_api_key', 'shorts_autofetch_enabled',
        'shorts_autofetch_interval_hours', 'max_shorts_per_channel', 'auto_publish_fetched',
        'permalink_structure', 'ad_placements',
        // Facebook / Instagram
        'facebook_app_id', 'facebook_app_secret', 'facebook_page_id',
        'facebook_page_token', 'facebook_token_expires_at',
        'facebook_fetch_enabled', 'facebook_fetch_interval_hours',
        'facebook_content_filter', 'facebook_auto_refresh_token',
        // TikTok
        'tiktok_client_key', 'tiktok_client_secret', 'tiktok_access_token',
        'tiktok_token_expires_at', 'tiktok_open_id',
        'tiktok_fetch_enabled', 'tiktok_fetch_interval_hours',
        // YouTube Shorts extended
        'youtube_shorts_fetch_enabled', 'youtube_shorts_auto_link',
        'youtube_match_confidence_threshold',
        // Mobile app store links
        'app_store_url', 'play_store_url',
        // Email
        'welcome_email_enabled', 'welcome_email_subject', 'welcome_email_body_extra',
        // AdMob
        'admob_app_id_android', 'admob_app_id_ios',
        'admob_interstitial_id_android', 'admob_interstitial_id_ios',
        'admob_ad_frequency',
    ];

    public function sendTestWelcomeEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $settings = SiteSetting::firstOrNew(['id' => 1]);

        Mail::to($data['email'])->send(new WelcomeEmail(
            displayName: 'Test User',
            settings: $settings,
        ));

        return response()->json(['message' => 'Test welcome email sent to ' . $data['email']]);
    }

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
            'autosave_interval' => ['sometimes', 'nullable', 'integer', 'min:15', 'max:600'],
            'newsletter_popup_enabled' => ['sometimes', 'boolean'],
            'newsletter_popup_interval_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:168'],
            'newsletter_popup_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newsletter_popup_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'newsletter_popup_body' => ['sometimes', 'nullable', 'string'],
            'newsletter_popup_categories' => ['sometimes', 'nullable', 'array'],
            'newsletter_popup_categories.*' => ['string', 'max:120'],
            'youtube_api_key' => ['sometimes', 'nullable', 'string'],
            'shorts_autofetch_enabled' => ['sometimes', 'boolean'],
            'shorts_autofetch_interval_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:168'],
            'max_shorts_per_channel' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'auto_publish_fetched' => ['sometimes', 'boolean'],
            'permalink_structure' => ['sometimes', 'in:plain,type-slug,type-date-slug'],
            'ad_placements' => ['sometimes', 'nullable', 'array'],
            'app_store_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'play_store_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'welcome_email_enabled' => ['sometimes', 'boolean'],
            'welcome_email_subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'welcome_email_body_extra' => ['sometimes', 'nullable', 'string'],
            'admob_app_id_android' => ['sometimes', 'nullable', 'string', 'max:255'],
            'admob_app_id_ios' => ['sometimes', 'nullable', 'string', 'max:255'],
            'admob_interstitial_id_android' => ['sometimes', 'nullable', 'string', 'max:255'],
            'admob_interstitial_id_ios' => ['sometimes', 'nullable', 'string', 'max:255'],
            'admob_ad_frequency' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
        ]);

        // Filter to only columns that actually exist (defensive: migrations may be pending)
        $availableFields = array_filter(self::ALLOWED, fn ($field) => Schema::hasColumn('site_settings', $field));
        $payload = array_merge(['id' => 1], array_intersect_key($validated, array_flip($availableFields)));

        try {
            $setting = SiteSetting::updateOrCreate(['id' => 1], $payload);
            return response()->json($setting);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SiteSetting upsert failed', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
            return response()->json([
                'error' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
