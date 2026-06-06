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
        Schema::create('bike_blueprint_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->foreignId('product_id')->constrained('products');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bike_blueprint_id', 'product_id'], 'bike_blueprint_products_unique');
            $table->index(['bike_blueprint_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bike_blueprint_products');
    }
};
