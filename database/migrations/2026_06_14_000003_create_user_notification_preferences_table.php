<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->boolean('new_publications_enabled')->default(true);
            $table->boolean('subscribed_niches_only')->default(true);
            $table->boolean('weekly_digest_enabled')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('admin_profiles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
