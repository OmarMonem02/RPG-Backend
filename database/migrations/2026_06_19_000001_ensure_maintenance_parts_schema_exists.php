<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for environments where create_maintenance_parts_tables did not run
 * (e.g. deploy used migrate --graceful and the after('spare_part_id') clause failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maintenance_part_categories')) {
            Schema::create('maintenance_part_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('maintenance_parts')) {
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
                $table->longText('tags')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['maintenance_parts_category_id', 'brand_id']);
                $table->index('part_number');
            });
        }

        if (Schema::hasTable('bike_blueprints') && ! Schema::hasTable('bike_blueprint_maintenance_parts')) {
            Schema::create('bike_blueprint_maintenance_parts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
                $table->foreignId('maintenance_part_id')->constrained('maintenance_parts');
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['bike_blueprint_id', 'maintenance_part_id'], 'bb_mp_unique');
            });
        }

        if (Schema::hasTable('sale_items') && ! Schema::hasColumn('sale_items', 'maintenance_part_id')) {
            Schema::table('sale_items', function (Blueprint $table) {
                $table->foreignId('maintenance_part_id')->nullable()->constrained('maintenance_parts');
            });
        }

        if (Schema::hasTable('ticket_items') && ! Schema::hasColumn('ticket_items', 'maintenance_part_id')) {
            Schema::table('ticket_items', function (Blueprint $table) {
                $table->foreignId('maintenance_part_id')->nullable()->constrained('maintenance_parts');
            });
        }

        if (Schema::hasTable('maintenance_parts') && ! Schema::hasColumn('maintenance_parts', 'have_commission')) {
            Schema::table('maintenance_parts', function (Blueprint $table) {
                $table->boolean('have_commission')->default(true);
            });
        }
    }

    public function down(): void
    {
        // No-op: never revert safety migration changes.
    }
};
