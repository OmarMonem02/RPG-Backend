<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['products', 'spare_parts', 'bike_for_sale'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->enum('cost_currency', ['EGP', 'USD', 'EUR'])->default('EGP')->after('currency_pricing');
                $blueprint->enum('sale_currency', ['EGP', 'USD', 'EUR'])->default('EGP')->after('cost_currency');
                $blueprint->enum('sale_price_mode', ['manual', 'margin'])->default('manual')->after('sale_price');
                $blueprint->enum('sale_margin_type', ['percentage', 'fixed'])->nullable()->after('sale_price_mode');
                $blueprint->decimal('sale_margin_value', 14, 2)->nullable()->after('sale_margin_type');
            });
        }

        foreach ($this->tables as $table) {
            DB::table($table)->update([
                'cost_currency' => DB::raw('currency_pricing'),
                'sale_currency' => DB::raw('currency_pricing'),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn([
                    'cost_currency',
                    'sale_currency',
                    'sale_price_mode',
                    'sale_margin_type',
                    'sale_margin_value',
                ]);
            });
        }
    }
};
