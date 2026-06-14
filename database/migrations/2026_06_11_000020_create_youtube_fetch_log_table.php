<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('youtube_fetch_log')) {
            return;
        }

        Schema::create('youtube_fetch_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category_slug')->nullable()->index();
            $table->unsignedInteger('videos_found')->default(0);
            $table->unsignedInteger('videos_imported')->default(0);
            $table->unsignedInteger('videos_skipped')->default(0);
            $table->string('status', 40)->index();
            $table->text('error_message')->nullable();
            $table->string('triggered_by', 20)->default('manual');
            $table->uuid('triggered_by_admin')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_fetch_log');
    }
};
