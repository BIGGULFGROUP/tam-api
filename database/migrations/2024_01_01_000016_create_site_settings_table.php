<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('site_name')->default('The African Mail');
            $table->string('tagline')->nullable();
            $table->string('og_image_url')->nullable();
            $table->string('social_youtube_url')->nullable();
            $table->string('social_instagram_url')->nullable();
            $table->string('social_x_url')->nullable();
            $table->string('social_tiktok_url')->nullable();
            $table->string('ga4_id')->nullable();
            $table->string('gtm_id')->nullable();
            $table->string('adsense_id')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->boolean('comments_enabled')->default(true);
            $table->boolean('newsletter_enabled')->default(true);
            $table->boolean('newsletter_popup_enabled')->default(true);
            $table->unsignedSmallInteger('newsletter_popup_interval_hours')->default(24);
            $table->string('newsletter_popup_template')->nullable();
            $table->string('newsletter_popup_title')->nullable();
            $table->text('newsletter_popup_body')->nullable();
            $table->json('newsletter_popup_categories')->nullable();
            $table->string('youtube_api_key')->nullable();
            $table->boolean('shorts_autofetch_enabled')->default(false);
            $table->unsignedSmallInteger('shorts_autofetch_interval_hours')->default(24);
            $table->unsignedSmallInteger('max_shorts_per_channel')->default(10);
            $table->string('permalink_structure')->default('/{slug}');
            $table->json('ad_placements')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
