<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $tables = [
            'products' => 'currency_pricing',
            'spare_parts' => 'currency_pricing',
            'maintenance_services' => 'currency_pricing',
            'bike_for_sale' => 'currency_pricing',
            'expenses' => 'currency',
        ];

        foreach ($tables as $table => $column) {
            DB::statement(sprintf(
                "ALTER TABLE `%s` MODIFY `%s` ENUM('EGP','USD','EUR') NOT NULL",
                $table,
                $column
            ));
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $tables = [
            'products' => 'currency_pricing',
            'spare_parts' => 'currency_pricing',
            'maintenance_services' => 'currency_pricing',
            'bike_for_sale' => 'currency_pricing',
            'expenses' => 'currency',
        ];

        foreach ($tables as $table => $column) {
            DB::statement(sprintf(
                "ALTER TABLE `%s` MODIFY `%s` ENUM('EGP','USD') NOT NULL",
                $table,
                $column
            ));
        }
    }
};
