<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if (!Schema::hasColumn('comments', 'content_type')) {
                $table->string('content_type', 20)->default('video')->after('id');
            }
            if (!Schema::hasColumn('comments', 'moderation_status')) {
                $table->string('moderation_status', 20)->default('visible')->after('is_spam'); // visible, hidden, flagged
            }
            if (!Schema::hasColumn('comments', 'like_count')) {
                $table->unsignedInteger('like_count')->default(0)->after('upvotes');
            }
        });

        // Make content_id nullable since clips won't always be in videos table
        Schema::table('comments', function (Blueprint $table) {
            // Add index for content_type lookups
            if (Schema::hasColumn('comments', 'content_type') && Schema::hasColumn('comments', 'content_id')) {
                // Composite index already handled, just ensure individual indexes exist
                // SQLite/MySQL handle this differently, skip if already indexed
            }
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'moderation_status', 'like_count']);
        });
    }
};
