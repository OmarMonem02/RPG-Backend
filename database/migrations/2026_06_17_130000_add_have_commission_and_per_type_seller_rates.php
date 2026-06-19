<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'spare_parts', 'maintenance_parts', 'bike_for_sale'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'have_commission')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->boolean('have_commission')->default(true);
            });
        }

        if (Schema::hasTable('maintenance_services') && ! Schema::hasColumn('maintenance_services', 'have_commission')) {
            Schema::table('maintenance_services', function (Blueprint $table) {
                $table->boolean('have_commission')->default(true);
            });
        }

        Schema::table('sellers', function (Blueprint $table) {
            if (! Schema::hasColumn('sellers', 'products_commission_rate')) {
                $table->decimal('products_commission_rate', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('sellers', 'spare_parts_commission_rate')) {
                $table->decimal('spare_parts_commission_rate', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('sellers', 'maintenance_parts_commission_rate')) {
                $table->decimal('maintenance_parts_commission_rate', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('sellers', 'bikes_for_sale_commission_rate')) {
                $table->decimal('bikes_for_sale_commission_rate', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('sellers', 'maintenance_services_commission_rate')) {
                $table->decimal('maintenance_services_commission_rate', 8, 2)->default(0);
            }
        });

        if (Schema::hasColumn('sellers', 'commission_rate')) {
            DB::table('sellers')->update([
                'products_commission_rate' => DB::raw('commission_rate'),
                'spare_parts_commission_rate' => DB::raw('commission_rate'),
                'maintenance_parts_commission_rate' => DB::raw('commission_rate'),
                'bikes_for_sale_commission_rate' => DB::raw('commission_rate'),
                'maintenance_services_commission_rate' => DB::raw('commission_rate'),
            ]);

            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('commission_rate');
            });
        }
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->decimal('commission_rate', 8, 2)->default(0)->after('phone');
        });

        DB::table('sellers')->update([
            'commission_rate' => DB::raw('products_commission_rate'),
        ]);

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn([
                'products_commission_rate',
                'spare_parts_commission_rate',
                'maintenance_parts_commission_rate',
                'bikes_for_sale_commission_rate',
                'maintenance_services_commission_rate',
            ]);
        });

        foreach (['products', 'spare_parts', 'maintenance_parts', 'bike_for_sale', 'maintenance_services'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('have_commission');
            });
        }
    }
};
