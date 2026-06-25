<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contributor_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('full_name');
            $table->text('bio')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->json('expertise_areas')->nullable();
            $table->json('content_types')->nullable();
            $table->text('motivation')->nullable();
            $table->string('status')->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('admin_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contributor_applications');
    }
};
