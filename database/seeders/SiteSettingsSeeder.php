<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SiteSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // site_settings uses a singleton row with id=1
        DB::table('site_settings')->insertOrIgnore([
            'id'                  => 1,
            'site_name'           => 'The African Mail',
            'permalink_structure' => 'plain',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }
}
