<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_read_events', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('niche')->index();
            $table->string('country_code', 2)->nullable()->after('ip_address')->index();
            $table->string('country_name')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('user_read_events', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'country_code', 'country_name']);
        });
    }
};
