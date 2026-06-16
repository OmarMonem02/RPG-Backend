<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $catalogTables = ['products', 'spare_parts', 'bike_for_sale'];

    public function up(): void
    {
        if (Schema::hasColumn('maintenance_services', 'currency_pricing')) {
            Schema::table('maintenance_services', function (Blueprint $blueprint): void {
                $blueprint->enum('sale_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            });

            DB::table('maintenance_services')->update([
                'sale_currency' => DB::raw('currency_pricing'),
            ]);

            Schema::table('maintenance_services', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('currency_pricing');
            });
        }

        foreach ($this->catalogTables as $table) {
            if (Schema::hasColumn($table, 'currency_pricing')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('currency_pricing');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->catalogTables as $table) {
            if (! Schema::hasColumn($table, 'currency_pricing')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->enum('currency_pricing', ['EGP', 'USD', 'EUR'])->default('EGP')->after('sale_currency');
                });

                DB::table($table)->update([
                    'currency_pricing' => DB::raw('sale_currency'),
                ]);
            }
        }

        if (! Schema::hasColumn('maintenance_services', 'currency_pricing')) {
            Schema::table('maintenance_services', function (Blueprint $blueprint): void {
                $blueprint->enum('currency_pricing', ['EGP', 'USD', 'EUR'])->default('EGP');
            });

            DB::table('maintenance_services')->update([
                'currency_pricing' => DB::raw('sale_currency'),
            ]);

            Schema::table('maintenance_services', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('sale_currency');
            });
        }
    }
};
