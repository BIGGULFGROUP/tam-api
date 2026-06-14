<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_id');
            $table->uuid('actor_id')->nullable();
            $table->string('actor_label')->nullable();
            $table->string('event_type');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('submission_id')
                  ->references('id')->on('content_submissions')
                  ->onDelete('cascade');
            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_events');
    }
};
