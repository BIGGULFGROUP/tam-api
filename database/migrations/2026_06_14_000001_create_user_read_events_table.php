<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_read_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->uuid('content_id')->nullable()->index();
            $table->string('slug')->nullable()->index();
            $table->string('niche')->nullable()->index();
            $table->timestamp('viewed_at')->useCurrent()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('admin_profiles')->onDelete('cascade');
            $table->foreign('content_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_read_events');
    }
};
