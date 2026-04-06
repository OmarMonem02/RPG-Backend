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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('permissions')->insert([
            ['name' => 'view_sales', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'create_sale', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'edit_sale', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'view_inventory', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'edit_inventory', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'view_reports', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'manage_users', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'view_tickets', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'update_tasks', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
