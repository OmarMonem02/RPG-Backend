<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@rpg.com'],
            [
                'name' => 'System Admin',
                'password' => 'password123',
                'role' => User::ROLE_ADMIN,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'staff@rpg.com'],
            [
                'name' => 'System Staff',
                'password' => 'password123',
                'role' => User::ROLE_STAFF,
            ]
        );
        User::query()->updateOrCreate(
            ['email' => 'technician@rpg.com'],
            [
                'name' => 'System Technician',
                'password' => 'password123',
                'role' => User::ROLE_TECHNICIAN,
            ]
        );
    }
}
