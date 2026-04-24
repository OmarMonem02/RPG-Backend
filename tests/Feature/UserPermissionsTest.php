<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_full_permission_matrix_and_get_it_back(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $payload = $this->permissionPayload([
            'sales' => ['read', 'create', 'read', 'update'],
            'maintenance' => ['read'],
            'users' => ['read', 'update'],
        ]);

        $response = $this->actingAs($admin)->putJson("/api/users/{$staff->id}/permissions", $payload);

        $response->assertOk()
            ->assertJsonPath('user.id', $staff->id)
            ->assertJsonPath('user.permissions.sales', ['create', 'read', 'update'])
            ->assertJsonPath('user.permissions.maintenance', ['read'])
            ->assertJsonPath('user.permissions.users', ['read', 'update']);

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
        ]);

        $this->assertSame(
            ['create', 'read', 'update'],
            $staff->fresh()->permissions_override['sales']
        );
    }

    public function test_non_admin_cannot_update_permissions(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $target = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)
            ->putJson("/api/users/{$target->id}/permissions", $this->permissionPayload())
            ->assertStatus(403);
    }

    public function test_permissions_update_rejects_unknown_missing_and_invalid_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = $this->permissionPayload();

        unset($payload['permissions']['inventory']);
        $payload['permissions']['unknown-page'] = ['read'];
        $payload['permissions']['sales'] = ['read', 'fly'];

        $this->actingAs($admin)
            ->putJson('/api/users/1/permissions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'permissions.inventory',
                'permissions.unknown-page',
                'permissions.sales.1',
            ]);
    }

    public function test_admin_cannot_remove_their_own_users_management_access(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $payload = $this->permissionPayload([
            'users' => ['read'],
        ]);

        $this->actingAs($admin)
            ->putJson("/api/users/{$admin->id}/permissions", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.users']);
    }

    public function test_login_me_and_user_show_return_effective_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        $admin->forceFill([
            'permissions_override' => $this->permissionMatrix([
                'users' => ['read', 'update'],
                'sales' => ['read'],
            ]),
        ])->save();

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('user.permissions.users', ['read', 'update'])
            ->assertJsonPath('user.permissions.sales', ['read']);

        $this->actingAs($admin)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.permissions.users', ['read', 'update']);

        $this->actingAs($admin)
            ->getJson("/api/users/{$admin->id}")
            ->assertOk()
            ->assertJsonPath('user.permissions.users', ['read', 'update']);
    }

    public function test_default_role_behavior_still_works_without_override(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $technician = User::factory()->create(['role' => User::ROLE_TECHNICIAN]);

        $this->actingAs($staff)
            ->getJson('/api/sales/catalog-items')
            ->assertOk();

        $this->actingAs($technician)
            ->getJson('/api/sales/catalog-items')
            ->assertStatus(403);

        $this->actingAs($technician)
            ->getJson('/api/tickets')
            ->assertOk();
    }

    public function test_override_can_restrict_and_expand_access_relative_to_role_defaults(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->permissionMatrix([
                'maintenance' => ['read'],
            ]),
        ]);

        $technician = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => $this->permissionMatrix([
                'maintenance' => ['read'],
                'users' => ['read'],
            ]),
        ]);

        $this->actingAs($staff)
            ->getJson('/api/sales/catalog-items')
            ->assertStatus(403);

        $this->actingAs($technician)
            ->getJson('/api/users')
            ->assertOk();
    }

    public function test_permission_middleware_enforces_sales_maintenance_users_and_import_export_routes(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->permissionMatrix([
                'sales' => ['read'],
                'maintenance' => ['read'],
                'users' => ['read'],
                'import-export' => ['read'],
            ]),
        ]);

        $this->actingAs($user)
            ->getJson('/api/sales/catalog-items')
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/api/tickets', [])
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/users')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/import-export/entities')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/import-export/products/export')
            ->assertStatus(403);
    }

    private function permissionPayload(array $overrides = []): array
    {
        return [
            'permissions' => $this->permissionMatrix($overrides),
        ];
    }

    private function permissionMatrix(array $overrides = []): array
    {
        return UserPermissions::normalizeMatrix(array_replace(
            array_fill_keys(UserPermissions::pages(), []),
            $overrides
        ));
    }
}
