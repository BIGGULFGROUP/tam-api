<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $table = 'site_settings';

    // Singleton row — no auto-incrementing primary key needed
    public $incrementing = false;

    protected $fillable = [
        'id', 'site_name', 'tagline', 'og_image_url',
        'social_youtube_url', 'social_facebook_url', 'social_instagram_url', 'social_linkedin_url', 'social_x_url', 'social_tiktok_url',
        'ga4_id', 'gtm_id', 'adsense_id',
        'maintenance_mode', 'comments_enabled', 'newsletter_enabled',
        'articles_enabled', 'review_workflow', 'autosave_interval',
        'newsletter_popup_enabled', 'newsletter_popup_interval_hours',
        'newsletter_popup_template', 'newsletter_popup_title',
        'newsletter_popup_body', 'newsletter_popup_categories',
        'youtube_api_key', 'shorts_autofetch_enabled',
        'shorts_autofetch_interval_hours', 'max_shorts_per_channel',
        'auto_publish_fetched',
        'permalink_structure', 'ad_placements',
        // Mobile app
        'app_store_url', 'play_store_url',
        // Email
        'welcome_email_enabled', 'welcome_email_subject', 'welcome_email_body_extra',
        // AdMob
        'admob_app_id_android', 'admob_app_id_ios',
        'admob_interstitial_id_android', 'admob_interstitial_id_ios',
        'admob_ad_frequency',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode'                 => 'boolean',
            'comments_enabled'                 => 'boolean',
            'newsletter_enabled'               => 'boolean',
            'articles_enabled'                 => 'boolean',
            'review_workflow'                  => 'boolean',
            'newsletter_popup_enabled'         => 'boolean',
            'shorts_autofetch_enabled'         => 'boolean',
            'auto_publish_fetched'             => 'boolean',
            'welcome_email_enabled'            => 'boolean',
            'newsletter_popup_categories'      => 'array',
            'ad_placements'                    => 'array',
            'autosave_interval'                => 'integer',
            'newsletter_popup_interval_hours'  => 'integer',
            'shorts_autofetch_interval_hours'  => 'integer',
            'max_shorts_per_channel'           => 'integer',
            'admob_ad_frequency'               => 'integer',
        ];
    }
}
