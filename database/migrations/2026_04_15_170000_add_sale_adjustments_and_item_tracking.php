<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_qty')->default(0)->after('qty');
            $table->string('status')->default('active')->after('returned_qty');
            $table->foreignId('replaced_from_sale_item_id')
                ->nullable()
                ->after('status')
                ->constrained('sale_items')
                ->nullOnDelete();

            $table->index('status');
            $table->index('replaced_from_sale_item_id');
        });

        Schema::create('sale_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->text('summary');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->decimal('amount_delta', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->decimal('extra_amount_due', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'action_type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_adjustments');

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['replaced_from_sale_item_id']);
            $table->dropConstrainedForeignId('replaced_from_sale_item_id');
            $table->dropColumn(['status', 'returned_qty']);
        });
    }
};
