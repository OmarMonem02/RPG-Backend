<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->decimal('discount', 12, 2)->default(0)->after('total');
        });

        Schema::table('approval_requests', function (Blueprint $table) {
            $table->foreignId('consumed_ticket_id')
                ->nullable()
                ->after('consumed_sale_id')
                ->constrained('tickets')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumed_ticket_id');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
