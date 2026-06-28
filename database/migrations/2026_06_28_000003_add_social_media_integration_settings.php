<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Facebook / Instagram Reels
            if (!Schema::hasColumn('site_settings', 'facebook_app_id')) {
                $table->string('facebook_app_id', 255)->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'facebook_app_secret')) {
                $table->text('facebook_app_secret')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'facebook_page_id')) {
                $table->string('facebook_page_id', 255)->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'facebook_page_token')) {
                $table->text('facebook_page_token')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'facebook_token_expires_at')) {
                $table->timestamp('facebook_token_expires_at')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'facebook_fetch_enabled')) {
                $table->boolean('facebook_fetch_enabled')->default(false);
            }
            if (!Schema::hasColumn('site_settings', 'facebook_fetch_interval_hours')) {
                $table->unsignedSmallInteger('facebook_fetch_interval_hours')->default(24);
            }
            if (!Schema::hasColumn('site_settings', 'facebook_content_filter')) {
                $table->string('facebook_content_filter', 50)->default('reels'); // reels, videos, photos, all
            }
            if (!Schema::hasColumn('site_settings', 'facebook_auto_refresh_token')) {
                $table->boolean('facebook_auto_refresh_token')->default(false);
            }

            // TikTok
            if (!Schema::hasColumn('site_settings', 'tiktok_client_key')) {
                $table->string('tiktok_client_key', 255)->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_client_secret')) {
                $table->text('tiktok_client_secret')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_access_token')) {
                $table->text('tiktok_access_token')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_token_expires_at')) {
                $table->timestamp('tiktok_token_expires_at')->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_open_id')) {
                $table->string('tiktok_open_id', 255)->nullable();
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_fetch_enabled')) {
                $table->boolean('tiktok_fetch_enabled')->default(false);
            }
            if (!Schema::hasColumn('site_settings', 'tiktok_fetch_interval_hours')) {
                $table->unsignedSmallInteger('tiktok_fetch_interval_hours')->default(24);
            }

            // YouTube Shorts extended settings
            if (!Schema::hasColumn('site_settings', 'youtube_shorts_fetch_enabled')) {
                $table->boolean('youtube_shorts_fetch_enabled')->default(false);
            }
            if (!Schema::hasColumn('site_settings', 'youtube_shorts_auto_link')) {
                $table->boolean('youtube_shorts_auto_link')->default(false);
            }
            if (!Schema::hasColumn('site_settings', 'youtube_match_confidence_threshold')) {
                $table->unsignedSmallInteger('youtube_match_confidence_threshold')->default(70); // 50-100
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'facebook_app_id', 'facebook_app_secret', 'facebook_page_id',
                'facebook_page_token', 'facebook_token_expires_at',
                'facebook_fetch_enabled', 'facebook_fetch_interval_hours',
                'facebook_content_filter', 'facebook_auto_refresh_token',
                'tiktok_client_key', 'tiktok_client_secret', 'tiktok_access_token',
                'tiktok_token_expires_at', 'tiktok_open_id',
                'tiktok_fetch_enabled', 'tiktok_fetch_interval_hours',
                'youtube_shorts_fetch_enabled', 'youtube_shorts_auto_link',
                'youtube_match_confidence_threshold',
            ]);
        });
    }
};
