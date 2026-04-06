<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('value', 15, 4)->nullable()->after('currency');
            $table->foreignId('updated_by')->nullable()->after('value')->constrained('users')->nullOnDelete();
        });

        DB::table('exchange_rates')
            ->whereNull('value')
            ->update(['value' => DB::raw('rate')]);

        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropUnique('exchange_rates_currency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->unique('currency');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('value');
        });
    }
};
