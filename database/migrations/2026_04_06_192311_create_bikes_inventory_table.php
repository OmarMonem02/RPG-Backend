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
        Schema::create('bikes_inventory', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['owned', 'consignment']);
            $table->string('brand')->index();
            $table->string('model')->index();
            $table->year('year')->index();
            $table->decimal('cost_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->string('owner_name', 255)->nullable();
            $table->string('owner_phone', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bikes_inventory');
    }
};
