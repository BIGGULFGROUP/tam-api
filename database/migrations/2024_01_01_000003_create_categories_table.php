<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->string('short_label')->nullable();
            $table->text('description')->nullable();
            $table->text('about')->nullable();
            $table->string('accent_color')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('icon')->nullable();
            $table->string('youtube_channel_id')->nullable();
            $table->string('youtube_channel_name')->nullable();
            $table->string('youtube_playlist_id')->nullable();
            $table->boolean('auto_fetch_enabled')->default(false);
            $table->unsignedSmallInteger('fetch_interval_hours')->default(24);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('featured_publication_slug')->nullable();
            $table->string('spotlight_title')->nullable();
            $table->string('subscribe_title')->nullable();
            $table->text('subscribe_body')->nullable();
            $table->string('newsletter_title')->nullable();
            $table->text('newsletter_body')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('content_count')->default(0);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
