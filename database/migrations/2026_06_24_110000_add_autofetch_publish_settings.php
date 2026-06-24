<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add auto_publish_fetched setting and fetch_interval_hours to site_settings.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('site_settings', 'auto_publish_fetched')) {
                $table->boolean('auto_publish_fetched')->default(false)->after('max_shorts_per_channel');
            }
        });

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'fetch_interval_hours')) {
                $table->unsignedSmallInteger('fetch_interval_hours')->nullable()->after('auto_fetch_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('auto_publish_fetched');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('fetch_interval_hours');
        });
    }
};
