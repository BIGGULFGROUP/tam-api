<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migration.
     * Seeds the categories table directly so Render's preDeployCommand
     * (php artisan migrate --force) handles seeding without a separate step.
     */
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $categories = [
            ['slug' => 'history',          'label' => 'History',          'youtube_channel_username' => null, 'sort_order' => 0,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'politics',         'label' => 'Politics',         'youtube_channel_username' => null, 'sort_order' => 1,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'film',             'label' => 'Film',             'youtube_channel_username' => null, 'sort_order' => 2,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'sports',           'label' => 'Sports',           'youtube_channel_username' => null, 'sort_order' => 3,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'culture',          'label' => 'Culture',          'youtube_channel_username' => null, 'sort_order' => 4,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'news',             'label' => 'News',             'youtube_channel_username' => null, 'sort_order' => 5,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'global',           'label' => 'Global',           'youtube_channel_username' => null, 'sort_order' => 6,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'profiles',         'label' => 'Profiles',         'youtube_channel_username' => null, 'sort_order' => 7,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'tech-innovation',  'label' => 'Tech & Innovation','youtube_channel_username' => null, 'sort_order' => 8,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'business',         'label' => 'Business',         'youtube_channel_username' => null, 'sort_order' => 9,  'is_active' => true, 'auto_fetch_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($categories as $data) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $data['slug']],
                $data
            );
        }
    }

    /**
     * Reverse the migration (no-op — we don't delete seeded data).
     */
    public function down(): void
    {
        // No-op: don't destroy user data on rollback
    }
};
