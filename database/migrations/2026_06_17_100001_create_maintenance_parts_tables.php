<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('part_number')->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->enum('item_status', ['new', 'used'])->default('new');
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_alarm')->default(0);
            $table->foreignId('maintenance_parts_category_id')->constrained('maintenance_part_categories');
            $table->enum('cost_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            $table->enum('sale_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            $table->decimal('cost_price', 14, 2);
            $table->decimal('sale_price', 14, 2);
            $table->enum('sale_price_mode', ['manual', 'margin'])->default('manual');
            $table->enum('sale_margin_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('sale_margin_value', 14, 2)->nullable();
            $table->foreignId('brand_id')->constrained('brands');
            $table->enum('max_discount_type', ['fixed', 'percentage']);
            $table->decimal('max_discount_value', 14, 2)->default(0);
            $table->boolean('universal')->default(false);
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['maintenance_parts_category_id', 'brand_id']);
            $table->index('part_number');
        });

        Schema::create('bike_blueprint_maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->foreignId('maintenance_part_id')->constrained('maintenance_parts');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bike_blueprint_id', 'maintenance_part_id'], 'bb_mp_unique');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('maintenance_part_id')->nullable()->after('spare_part_id')->constrained('maintenance_parts');
        });

        Schema::table('ticket_items', function (Blueprint $table) {
            $table->foreignId('maintenance_part_id')->nullable()->after('spare_part_id')->constrained('maintenance_parts');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_part_id');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_part_id');
        });

        Schema::dropIfExists('bike_blueprint_maintenance_parts');
        Schema::dropIfExists('maintenance_parts');
        Schema::dropIfExists('maintenance_part_categories');
    }
};
