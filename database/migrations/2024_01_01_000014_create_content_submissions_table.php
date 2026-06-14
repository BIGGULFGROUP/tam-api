<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_id');
            $table->uuid('submitted_by');
            $table->string('author_label')->nullable();
            $table->string('author_role')->nullable();
            $table->string('submission_status')->default('submitted');
            $table->string('content_type')->nullable();
            $table->string('title')->nullable();
            $table->string('niche')->nullable();
            $table->json('tags')->nullable();
            $table->uuid('reviewer_id')->nullable();
            $table->string('reviewer_label')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('content_id');
            $table->index('submission_status');
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_submissions');
    }
};
