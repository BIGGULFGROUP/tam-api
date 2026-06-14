<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_popup_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('name');
            $table->string('title');
            $table->text('body');
            $table->unsignedSmallInteger('interval_hours')->default(24);
            $table->json('categories')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_popup_templates');
    }
};
