<?php

namespace Database\Seeders;

use App\Models\AdminProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminProfileSeeder extends Seeder
{
    public function run(): void
    {
        // Creates a default super admin for local development.
        // Change credentials immediately after first login.
        AdminProfile::firstOrCreate(
            ['email' => 'admin@theafricanmail.com'],
            [
                'id'           => Str::uuid(),
                'password'     => bcrypt('Admin@1234'),
                'display_name' => 'Super Admin',
                'full_name'    => 'Super Admin',
                'username'     => 'superadmin',
                'role'         => 'super_admin',
                'is_active'    => true,
                'is_public'    => false,
            ]
        );
    }
}
