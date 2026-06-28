<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'history',          'label' => 'History',          'youtube_channel_username' => null, 'sort_order' => 0, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/aqz-KE-bpKQ/maxresdefault.jpg', 'accent_color' => '#b8860b'],
            ['slug' => 'politics',         'label' => 'Politics',         'youtube_channel_username' => null, 'sort_order' => 1, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/3JZ_D3ELwOQ/maxresdefault.jpg', 'accent_color' => '#1a3a7a'],
            ['slug' => 'film',             'label' => 'Film',             'youtube_channel_username' => null, 'sort_order' => 2, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/JGwWNGJdvx8/maxresdefault.jpg', 'accent_color' => '#a01a1a'],
            ['slug' => 'sports',           'label' => 'Sports',           'youtube_channel_username' => null, 'sort_order' => 3, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/kJQP7kiw5Fk/maxresdefault.jpg', 'accent_color' => '#1a6b3a'],
            ['slug' => 'culture',          'label' => 'Culture',          'youtube_channel_username' => null, 'sort_order' => 4, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/iG9CE55wbtY/maxresdefault.jpg', 'accent_color' => '#7b3fa0'],
            ['slug' => 'news',             'label' => 'News',             'youtube_channel_username' => null, 'sort_order' => 5, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/OPf0YbXqDm0/maxresdefault.jpg', 'accent_color' => '#8a1a1a'],
            ['slug' => 'global',           'label' => 'Global',           'youtube_channel_username' => null, 'sort_order' => 6, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/YQHsXMglC9A/maxresdefault.jpg', 'accent_color' => '#2a7a6a'],
            ['slug' => 'profiles',         'label' => 'Profiles',         'youtube_channel_username' => null, 'sort_order' => 7, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/60ItHLz5WEA/maxresdefault.jpg', 'accent_color' => '#5a4a3a'],
            ['slug' => 'tech-innovation',  'label' => 'Tech & Innovation','youtube_channel_username' => null, 'sort_order' => 8, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/2Vv-BfVoq4g/maxresdefault.jpg', 'accent_color' => '#155e75'],
            ['slug' => 'business',         'label' => 'Business',         'youtube_channel_username' => null, 'sort_order' => 9, 'is_active' => true, 'auto_fetch_enabled' => true, 'cover_image_url' => 'https://img.youtube.com/vi/fRh_vgS2dFE/maxresdefault.jpg', 'accent_color' => '#5b3a1f'],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
