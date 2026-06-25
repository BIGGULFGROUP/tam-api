<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('site_settings', 'social_facebook_url')) {
                $table->string('social_facebook_url', 500)->nullable()->after('social_youtube_url');
            }
            if (!Schema::hasColumn('site_settings', 'social_linkedin_url')) {
                $table->string('social_linkedin_url', 500)->nullable()->after('social_instagram_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn(['social_facebook_url', 'social_linkedin_url']);
        });
    }
};
