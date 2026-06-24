<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('status', 32)->default('pending');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('requested_discount_amount', 12, 2)->default(0);
            $table->decimal('approved_discount_amount', 12, 2)->nullable();
            $table->string('discount_input_type', 16)->default('fixed');
            $table->decimal('discount_input_value', 12, 2)->default(0);
            $table->string('approved_discount_input_type', 16)->nullable();
            $table->decimal('approved_discount_input_value', 12, 2)->nullable();
            $table->decimal('cart_subtotal', 12, 2)->default(0);
            $table->text('rejection_reason')->nullable();
            $table->json('payload');
            $table->timestamp('consumed_at')->nullable();
            $table->foreignId('consumed_sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('consumed_ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['requested_by', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
