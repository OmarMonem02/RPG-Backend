<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->boolean('is_unstored')->default(false);
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('unstored_type', 32)->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
        });

        Schema::table('ticket_items', function (Blueprint $table): void {
            $table->boolean('is_unstored')->default(false);
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('unstored_type', 32)->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['sale_items', 'ticket_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn([
                    'is_unstored',
                    'custom_name',
                    'custom_description',
                    'unstored_type',
                    'cost_price',
                ]);
            });
        }
    }
};
