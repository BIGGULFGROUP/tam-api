<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_tags', function (Blueprint $table) {
            $table->id();
            $table->uuid('content_id');
            $table->uuid('tag_id');
            $table->timestamps();

            $table->unique(['content_id', 'tag_id']);
            $table->foreign('content_id')->references('id')->on('videos')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_tags');
    }
};
