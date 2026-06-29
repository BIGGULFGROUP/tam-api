<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path')->index();
            $table->string('referrer')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('country_name')->nullable();
            $table->string('device_type', 20)->nullable()->index(); // desktop | mobile | tablet
            $table->string('browser', 30)->nullable();
            $table->string('os', 30)->nullable();
            $table->string('content_id', 36)->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['created_at', 'path']);
            $table->index(['created_at', 'device_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
