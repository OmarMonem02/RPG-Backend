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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->string('item_type'); // product or bike_inventory
            $table->unsignedBigInteger('item_id'); // product_id or bikes_inventory_id
            $table->decimal('price_snapshot', 15, 2);
            $table->integer('qty')->default(1);
            $table->decimal('discount', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index(['item_type', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
