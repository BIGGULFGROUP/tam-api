<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'history',          'label' => 'History',          'youtube_channel_username' => null, 'sort_order' => 0, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'politics',         'label' => 'Politics',         'youtube_channel_username' => null, 'sort_order' => 1, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'film',             'label' => 'Film',             'youtube_channel_username' => null, 'sort_order' => 2, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'sports',           'label' => 'Sports',           'youtube_channel_username' => null, 'sort_order' => 3, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'culture',          'label' => 'Culture',          'youtube_channel_username' => null, 'sort_order' => 4, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'news',             'label' => 'News',             'youtube_channel_username' => null, 'sort_order' => 5, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'global',           'label' => 'Global',           'youtube_channel_username' => null, 'sort_order' => 6, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'profiles',         'label' => 'Profiles',         'youtube_channel_username' => null, 'sort_order' => 7, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'tech-innovation',  'label' => 'Tech & Innovation','youtube_channel_username' => null, 'sort_order' => 8, 'is_active' => true, 'auto_fetch_enabled' => true],
            ['slug' => 'business',         'label' => 'Business',         'youtube_channel_username' => null, 'sort_order' => 9, 'is_active' => true, 'auto_fetch_enabled' => true],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
