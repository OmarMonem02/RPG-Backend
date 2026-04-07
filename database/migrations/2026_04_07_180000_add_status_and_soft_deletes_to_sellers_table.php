<?php

use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table): void {
            $table->string('status')->default(Seller::STATUS_ACTIVE)->after('commission_value');
            $table->softDeletes();
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->index(['seller_id', 'created_at']);
            $table->index(['seller_id', 'status']);
        });

        DB::table('sellers')->whereNull('status')->update([
            'status' => Seller::STATUS_ACTIVE,
        ]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['seller_id', 'created_at']);
            $table->dropIndex(['seller_id', 'status']);
        });

        Schema::table('sellers', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('status');
        });
    }
};
