<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            Permission::VIEW_SALES,
            Permission::CREATE_SALE,
            Permission::EDIT_SALE,
            Permission::VIEW_INVENTORY,
            Permission::EDIT_INVENTORY,
            Permission::VIEW_REPORTS,
            Permission::MANAGE_USERS,
            Permission::VIEW_TICKETS,
            Permission::UPDATE_TASKS,
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate(['name' => $permission]);
        }

        $permissionIds = Permission::query()->pluck('id', 'name');

        foreach ($permissionIds as $id) {
            DB::table('role_permissions')->updateOrInsert([
                'role' => 'admin',
                'permission_id' => $id,
            ], []);
        }

        foreach ([Permission::VIEW_SALES, Permission::CREATE_SALE, Permission::EDIT_SALE, Permission::VIEW_INVENTORY, Permission::EDIT_INVENTORY, Permission::VIEW_TICKETS, Permission::UPDATE_TASKS] as $permission) {
            DB::table('role_permissions')->updateOrInsert([
                'role' => 'staff',
                'permission_id' => $permissionIds[$permission],
            ], []);
        }

        foreach ([Permission::VIEW_TICKETS, Permission::UPDATE_TASKS] as $permission) {
            DB::table('role_permissions')->updateOrInsert([
                'role' => 'technician',
                'permission_id' => $permissionIds[$permission],
            ], []);
        }
    }
}
