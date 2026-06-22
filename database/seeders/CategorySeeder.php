<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'History',
            'Politics',
            'Arts & Culture',
            'World News',
            'Profile',
            'Tech & Innovation',
            'Business',
        ];

        foreach ($categories as $index => $label) {
            Category::updateOrCreate(
                ['slug' => Str::slug($label)],
                [
                    'label' => $label,
                    'sort_order' => $index,
                    'is_active' => true,
                ]
            );
        }
    }
}
