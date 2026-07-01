<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('app_store_url')->nullable()->after('adsense_id');
            $table->string('play_store_url')->nullable()->after('app_store_url');
            $table->boolean('welcome_email_enabled')->default(true)->after('play_store_url');
            $table->string('welcome_email_subject')->nullable()->after('welcome_email_enabled');
            $table->text('welcome_email_body_extra')->nullable()->after('welcome_email_subject');
            $table->string('admob_app_id_android')->nullable()->after('welcome_email_body_extra');
            $table->string('admob_app_id_ios')->nullable()->after('admob_app_id_android');
            $table->string('admob_interstitial_id_android')->nullable()->after('admob_app_id_ios');
            $table->string('admob_interstitial_id_ios')->nullable()->after('admob_interstitial_id_android');
            $table->integer('admob_ad_frequency')->default(5)->after('admob_interstitial_id_ios');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'app_store_url', 'play_store_url',
                'welcome_email_enabled', 'welcome_email_subject', 'welcome_email_body_extra',
                'admob_app_id_android', 'admob_app_id_ios',
                'admob_interstitial_id_android', 'admob_interstitial_id_ios',
                'admob_ad_frequency',
            ]);
        });
    }
};
