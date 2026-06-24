<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make youtube_channel_id nullable — it's an optional global site field.
     * Per-category channel config lives in the categories table.
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('youtube_channel_id')->nullable(false)->change();
        });
    }
};
