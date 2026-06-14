<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('content_type')->default('video'); // video | article | short
            $table->string('youtube_id')->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('niche'); // category slug
            $table->string('author')->nullable();
            $table->uuid('created_by')->nullable();
            $table->text('description')->nullable();
            $table->longText('body')->nullable();
            $table->json('tags')->nullable();
            $table->string('duration')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_breaking')->default(false);
            $table->string('featured_image_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('read_time')->default(1);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->string('og_image_url')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('niche');
            $table->index('content_type');
            $table->index('created_by');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
