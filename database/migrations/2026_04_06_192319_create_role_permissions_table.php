<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'technician']);
            $table->foreignId('permission_id')->constrained()->onDelete('cascade');
            $table->primary(['role', 'permission_id']);
        });

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $rows = [];

        foreach ($permissionIds as $id) {
            $rows[] = ['role' => 'admin', 'permission_id' => $id];
        }

        foreach (['view_sales', 'create_sale', 'edit_sale', 'view_inventory', 'edit_inventory', 'view_tickets', 'update_tasks'] as $permission) {
            if (isset($permissionIds[$permission])) {
                $rows[] = ['role' => 'staff', 'permission_id' => $permissionIds[$permission]];
            }
        }

        foreach (['view_tickets', 'update_tasks'] as $permission) {
            if (isset($permissionIds[$permission])) {
                $rows[] = ['role' => 'technician', 'permission_id' => $permissionIds[$permission]];
            }
        }

        DB::table('role_permissions')->insert($rows);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
