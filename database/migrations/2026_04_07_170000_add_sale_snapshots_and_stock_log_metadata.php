<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('item_name')->nullable()->after('item_id');
            $table->decimal('selling_price_at_time', 15, 2)->nullable()->after('price_snapshot');
            $table->decimal('cost_price_at_time', 15, 2)->nullable()->after('selling_price_at_time');
        });

        Schema::table('stock_logs', function (Blueprint $table) {
            $table->string('type')->default('adjustment')->after('product_id');
            $table->decimal('qty_before', 15, 4)->default(0)->after('qty');
            $table->decimal('qty_after', 15, 4)->default(0)->after('qty_before');
            $table->foreignId('user_id')->nullable()->after('reference_id')->constrained()->nullOnDelete();
        });

        DB::table('sale_items')->update([
            'item_name' => DB::raw("CASE WHEN item_name IS NULL THEN item_type ELSE item_name END"),
            'selling_price_at_time' => DB::raw('price_snapshot'),
        ]);

        DB::table('stock_logs')->update([
            'type' => DB::raw("CASE WHEN reference_type = 'sale' THEN 'sale' WHEN reference_type = 'return' THEN 'return' ELSE 'adjustment' END"),
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['type', 'qty_before', 'qty_after']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['item_name', 'selling_price_at_time', 'cost_price_at_time']);
        });
    }
};
