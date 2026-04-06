<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->enum('category', ['goods', 'bills', 'supplies'])->index();
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->date('expense_date')->index();
            $table->enum('paid_by', ['cash', 'bank'])->index();
            $table->string('attachment')->nullable(); // file path
            $table->boolean('is_recurring')->default(false);
            $table->enum('recurring_type', ['monthly', 'weekly', 'yearly', 'none'])->default('none');
            $table->foreignId('generated_from_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->date('source_period_date')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
