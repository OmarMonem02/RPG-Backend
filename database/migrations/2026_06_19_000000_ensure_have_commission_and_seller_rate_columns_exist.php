<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for environments where the original have_commission / seller rates migration
 * did not run (e.g. deploy used migrate --graceful and the after('notes') clause failed).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $commissionableTables = [
        'products',
        'spare_parts',
        'maintenance_parts',
        'bike_for_sale',
        'maintenance_services',
    ];

    /** @var list<string> */
    private array $sellerRateColumns = [
        'products_commission_rate',
        'spare_parts_commission_rate',
        'maintenance_parts_commission_rate',
        'bikes_for_sale_commission_rate',
        'maintenance_services_commission_rate',
    ];

    public function up(): void
    {
        foreach ($this->commissionableTables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'have_commission')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->boolean('have_commission')->default(true);
            });
        }

        if (! Schema::hasTable('sellers')) {
            return;
        }

        foreach ($this->sellerRateColumns as $column) {
            if (Schema::hasColumn('sellers', $column)) {
                continue;
            }

            Schema::table('sellers', function (Blueprint $table) use ($column) {
                $table->decimal($column, 8, 2)->default(0);
            });
        }

        if (! Schema::hasColumn('sellers', 'commission_rate')) {
            return;
        }

        $update = [];
        foreach ($this->sellerRateColumns as $column) {
            $update[$column] = DB::raw('commission_rate');
        }

        DB::table('sellers')->update($update);

        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }

    public function down(): void
    {
        // No-op: never revert safety migration changes.
    }
};
