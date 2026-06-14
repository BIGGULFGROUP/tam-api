<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_popup_events', function (Blueprint $table) {
            $table->id();
            $table->string('template_key');
            $table->string('event_type'); // impression | close | submit | click
            $table->string('category_slug')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index('template_key');
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_popup_events');
    }
};
