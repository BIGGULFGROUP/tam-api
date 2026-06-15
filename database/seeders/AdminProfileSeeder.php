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
        AdminProfile::updateOrCreate(
            ['email' => 'admin@theafricanmail.com'],
            [
                'password'     => bcrypt('Admin@1234'),
                'display_name' => 'Super Admin',
                'full_name'    => 'Super Admin',
                'username'     => 'superadmin',
                'role'         => 'super_admin',
                'is_active'    => true,
                'is_public'    => false,
            ]
        );

        // Seed a specific admin account requested by the developer.
        AdminProfile::updateOrCreate(
            ['email' => 'ahmaduabubakarr@gmail.com'],
            [
                // Default password: change immediately after first login
                'password'     => bcrypt('Admin@1234'),
                'display_name' => 'Ahmadu Abubakarr',
                'full_name'    => 'Ahmadu Abubakarr',
                'username'     => 'Muslim007',
                'role'         => 'super_admin',
                'is_active'    => true,
                'is_public'    => false,
            ]
        );
    }
}
