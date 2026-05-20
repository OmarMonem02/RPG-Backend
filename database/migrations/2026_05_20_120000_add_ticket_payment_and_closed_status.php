<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('payment_method', 64)->nullable()->after('total');
            $table->decimal('amount_paid', 14, 2)->default(0)->after('payment_method');
            $table->timestamp('closed_at')->nullable()->after('amount_paid');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE tickets MODIFY status ENUM('pending', 'in_progress', 'completed', 'closed') NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('tickets')->where('status', 'closed')->update(['status' => 'completed']);
            DB::statement(
                "ALTER TABLE tickets MODIFY status ENUM('pending', 'in_progress', 'completed') NOT NULL"
            );
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'amount_paid', 'closed_at']);
        });
    }
};
