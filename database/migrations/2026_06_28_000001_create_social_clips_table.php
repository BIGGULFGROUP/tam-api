<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_clips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform', 30); // youtube, facebook, tiktok
            $table->string('external_clip_id', 255); // ID from the source platform
            $table->string('title', 500)->nullable();
            $table->text('caption')->nullable();
            $table->string('thumbnail_url', 1000)->nullable();
            $table->string('clip_url', 1000)->nullable();
            $table->string('embed_url', 1000)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->json('platform_metadata')->nullable();
            $table->uuid('linked_video_id')->nullable();
            $table->string('mapping_status', 30)->default('unlinked'); // unlinked, auto_mapped, manually_mapped, confirmed
            $table->decimal('match_confidence', 5, 2)->nullable(); // 0.00-100.00
            $table->uuid('mapped_by')->nullable(); // admin who did the linking
            $table->timestamp('mapped_at')->nullable();
            $table->timestamps();

            $table->index('platform');
            $table->index('mapping_status');
            $table->index('linked_video_id');
            $table->index('external_clip_id');
            $table->index('fetched_at');
            $table->unique(['platform', 'external_clip_id']);
            $table->foreign('linked_video_id')->references('id')->on('videos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_clips');
    }
};
