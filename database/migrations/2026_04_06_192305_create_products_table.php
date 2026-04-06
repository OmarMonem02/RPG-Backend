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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['part', 'accessory']);
            $table->string('name');
            $table->string('sku')->unique()->index();
            $table->string('part_number')->nullable()->index();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('brand_id')->constrained()->onDelete('cascade');
            $table->integer('qty')->default(0);
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->decimal('cost_price_usd', 15, 2)->nullable();
            $table->enum('max_discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('max_discount_value', 15, 2)->default(0);
            $table->boolean('is_universal')->default(false);
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
