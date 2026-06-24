<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Public user profiles
        Schema::create('public_user_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique(); // FK to admin_profiles/users
            $table->string('display_name', 120);
            $table->string('username', 60)->unique();
            $table->text('bio')->nullable();
            $table->string('avatar_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('twitter_url', 500)->nullable();
            $table->string('instagram_url', 500)->nullable();
            $table->string('linkedin_url', 500)->nullable();
            $table->string('location', 120)->nullable();
            $table->boolean('is_public')->default(true);
            $table->json('notification_preferences')->nullable(); // {"news": true, "politics": false, ...}
            $table->timestamps();
        });

        // User favorites/bookmarks
        Schema::create('user_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('content_id');
            $table->timestamps();
            $table->unique(['user_id', 'content_id']);
            $table->foreign('content_id')->references('id')->on('videos')->cascadeOnDelete();
        });

        // Viewing history
        Schema::create('user_view_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('content_id')->nullable();
            $table->string('niche', 60)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();
            $table->index(['user_id', 'viewed_at']);
        });

        // Push token registry (for mobile app notifications)
        Schema::create('push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable(); // null = anonymous device
            $table->string('token', 500)->unique();
            $table->string('platform', 20)->default('expo'); // expo | fcm | apns
            $table->json('preferences')->nullable(); // niche filter
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Affiliate links (monetization)
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('product_name', 200);
            $table->text('description')->nullable();
            $table->string('url', 1000);
            $table->string('niche', 60)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affiliate_link_id');
            $table->uuid('content_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->foreign('affiliate_link_id')->references('id')->on('affiliate_links')->cascadeOnDelete();
        });

        // Sponsored content
        Schema::create('sponsored_content', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('link_url', 1000);
            $table->string('niche', 60)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('sponsored_content');
        Schema::dropIfExists('push_tokens');
        Schema::dropIfExists('user_view_history');
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('public_user_profiles');
    }
};
