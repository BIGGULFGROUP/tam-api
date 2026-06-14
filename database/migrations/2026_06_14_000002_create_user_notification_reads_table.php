<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->index();
            $table->uuid('content_id')->index();
            $table->timestamp('read_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'content_id']);
            $table->foreign('user_id')->references('id')->on('admin_profiles')->onDelete('cascade');
            $table->foreign('content_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_reads');
    }
};
