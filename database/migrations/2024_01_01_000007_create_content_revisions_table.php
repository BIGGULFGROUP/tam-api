<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_id');
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->uuid('saved_by')->nullable();
            $table->timestamps();

            $table->foreign('content_id')->references('id')->on('videos')->onDelete('cascade');
            $table->index('content_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
    }
};
