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
        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->onDelete('cascade');
            $table->foreignId('task_id')->nullable()->constrained('ticket_tasks')->onDelete('cascade');
            $table->string('item_type'); // product or service
            $table->unsignedBigInteger('item_id'); // product_id or service_id
            $table->decimal('price_snapshot', 15, 2);
            $table->enum('price_source', ['current', 'old'])->default('current');
            $table->integer('qty')->default(1);
            $table->timestamps();

            $table->index(['item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
    }
};
