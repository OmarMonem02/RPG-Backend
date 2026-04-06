<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->enum('type', ['sale', 'ticket'])->index();
            $table->unsignedBigInteger('reference_id')->index();
            $table->decimal('total', 15, 2);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('final_total', 15, 2);
            $table->enum('status', ['paid', 'partial', 'unpaid'])->default('unpaid')->index();
            $table->timestamps();

            $table->unique(['type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
