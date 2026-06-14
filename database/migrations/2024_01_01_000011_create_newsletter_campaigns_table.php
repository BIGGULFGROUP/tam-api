<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('newsletter_key')->unique();
            $table->string('normalized_title')->unique();
            $table->string('title');
            $table->text('body');
            $table->string('banner_url')->nullable();
            $table->json('categories')->nullable();
            $table->unsignedSmallInteger('fetch_interval_hours')->default(24);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_campaigns');
    }
};
