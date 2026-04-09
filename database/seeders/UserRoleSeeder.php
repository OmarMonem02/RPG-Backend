<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@rpg.hub'],
            ['name' => 'Admin User', 'password' => Hash::make('admin123'), 'role' => User::ROLE_ADMIN]
        );

        User::updateOrCreate(
            ['email' => 'staff@rpg.hub'],
            ['name' => 'Staff User', 'password' => Hash::make('staff123'), 'role' => User::ROLE_STAFF]
        );

        User::updateOrCreate(
            ['email' => 'technician@rpg.hub'],
            ['name' => 'Technician User', 'password' => Hash::make('technician123'), 'role' => User::ROLE_TECHNICIAN]
        );
    }
}
