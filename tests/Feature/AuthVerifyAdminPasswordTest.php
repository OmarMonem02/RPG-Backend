<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthVerifyAdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_admin_password_requires_admin_role(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($staff)
            ->postJson('/api/auth/verify-admin-password', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_verify_admin_password_rejects_invalid_password(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('correct-password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/auth/verify-admin-password', ['password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->actingAs($admin)
            ->postJson('/api/auth/verify-admin-password', ['password' => 'correct-password'])
            ->assertOk()
            ->assertJsonPath('verified', true);
    }
}
