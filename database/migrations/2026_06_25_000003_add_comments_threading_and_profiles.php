<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add threading + upvotes to comments
        Schema::table('comments', function (Blueprint $table) {
            if (!Schema::hasColumn('comments', 'parent_id')) {
                $table->uuid('parent_id')->nullable()->after('content_id');
                $table->foreign('parent_id')->references('id')->on('comments')->nullOnDelete();
            }
            if (!Schema::hasColumn('comments', 'upvotes')) {
                $table->integer('upvotes')->default(0)->after('is_spam');
            }
        });

        // Comment upvotes tracking
        if (!Schema::hasTable('comment_upvotes')) {
            Schema::create('comment_upvotes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('comment_id');
                $table->string('voter_ip', 45)->nullable();
                $table->uuid('user_id')->nullable();
                $table->timestamps();
                $table->unique(['comment_id', 'voter_ip']);
                $table->foreign('comment_id')->references('id')->on('comments')->cascadeOnDelete();
            });
        }

        // User notifications inbox
        if (!Schema::hasTable('user_notifications')) {
            Schema::create('user_notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('type', 60); // comment_reply, comment_upvote, new_content, system
                $table->text('message');
                $table->string('link', 500)->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'is_read']);
            });
        }

        // Avatar support on admin profiles
        Schema::table('admin_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('admin_profiles', 'avatar_url')) {
                $table->string('avatar_url', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('comment_upvotes');
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'upvotes']);
        });
        Schema::table('admin_profiles', function (Blueprint $table) {
            $table->dropColumn(['avatar_url']);
        });
    }
};
