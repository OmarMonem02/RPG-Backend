<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->decimal('products_commission_rate', 8, 2)->default(0);
            $table->decimal('spare_parts_commission_rate', 8, 2)->default(0);
            $table->decimal('maintenance_parts_commission_rate', 8, 2)->default(0);
            $table->decimal('bikes_for_sale_commission_rate', 8, 2)->default(0);
            $table->decimal('maintenance_services_commission_rate', 8, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('address')->nullable();
            $table->string('how_did_you_know_us')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('full_address');
            $table->string('city');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_default']);
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('spare_part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_part_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_service_sectors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('types')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bike_blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained('brands');
            $table->string('model');
            $table->year('year');
            $table->timestamps();
            $table->softDeletes();
            $table->index('brand_id');
        });

        Schema::create('maintenance_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('sale_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            $table->decimal('service_price', 14, 2);
            $table->enum('max_discount_type', ['fixed', 'percentage']);
            $table->decimal('max_discount_value', 14, 2)->default(0);
            $table->boolean('have_commission')->default(true);
            $table->foreignId('maintenance_service_sector_id')->constrained('maintenance_service_sectors');
            $table->timestamps();
            $table->softDeletes();
            $table->index('maintenance_service_sector_id');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('part_number')->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->enum('item_status', ['new', 'used'])->default('new');
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_alarm')->default(0);
            $table->foreignId('products_category_id')->constrained('product_categories');
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
            $table->boolean('have_commission')->default(true);
            $table->text('notes')->nullable();
            $table->longText('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['products_category_id', 'brand_id']);
            $table->index('part_number');
        });

        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('part_number')->nullable();
            $table->string('size', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->enum('item_status', ['new', 'used'])->default('new');
            $table->integer('stock_quantity')->default(0);
            $table->integer('low_stock_alarm')->default(0);
            $table->foreignId('spare_parts_category_id')->constrained('spare_part_categories');
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
            $table->boolean('have_commission')->default(true);
            $table->text('notes')->nullable();
            $table->longText('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['spare_parts_category_id', 'brand_id']);
            $table->index('part_number');
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
            $table->boolean('have_commission')->default(true);
            $table->text('notes')->nullable();
            $table->longText('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['maintenance_parts_category_id', 'brand_id']);
            $table->index('part_number');
        });

        Schema::create('bike_for_sale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->enum('cost_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            $table->enum('sale_currency', ['EGP', 'USD', 'EUR'])->default('EGP');
            $table->decimal('cost_price', 14, 2);
            $table->decimal('sale_price', 14, 2);
            $table->enum('sale_price_mode', ['manual', 'margin'])->default('manual');
            $table->enum('sale_margin_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('sale_margin_value', 14, 2)->nullable();
            $table->string('status');
            $table->enum('max_discount_type', ['fixed', 'percentage']);
            $table->decimal('max_discount_value', 14, 2)->default(0);
            $table->boolean('have_commission')->default(true);
            $table->string('vin')->unique();
            $table->integer('mileage')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['bike_blueprint_id', 'status']);
        });

        Schema::create('customer_bikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->string('image')->nullable();
            $table->string('image_public_id')->nullable();
            $table->string('vin')->nullable();
            $table->integer('mileage')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'bike_blueprint_id']);
            $table->index('vin');
        });

        Schema::create('bike_blueprint_spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->foreignId('spare_part_id')->constrained('spare_parts');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bike_blueprint_id', 'spare_part_id'], 'bike_blueprint_spare_parts_unique');
            $table->index(['bike_blueprint_id', 'spare_part_id']);
        });

        Schema::create('bike_blueprint_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->foreignId('product_id')->constrained('products');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bike_blueprint_id', 'product_id'], 'bike_blueprint_products_unique');
            $table->index(['bike_blueprint_id', 'product_id']);
        });

        Schema::create('bike_blueprint_maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_blueprint_id')->constrained('bike_blueprints');
            $table->foreignId('maintenance_part_id')->constrained('maintenance_parts');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['bike_blueprint_id', 'maintenance_part_id'], 'bb_mp_unique');
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('seller_id')->nullable()->constrained('sellers');
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            $table->enum('type', ['site', 'online', 'delivery']);
            $table->enum('status', ['completed', 'partial', 'pending']);
            $table->string('delivery_status')->nullable();
            $table->decimal('shipping_fee', 14, 2)->default(0);
            $table->boolean('is_maintenance')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'user_id', 'seller_id', 'payment_method_id']);
            $table->index(['status', 'type']);
        });

        Schema::create('customer_sale', function (Blueprint $table) {
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sale_id')->constrained('sales');
            $table->timestamps();
            $table->softDeletes();
            $table->primary(['customer_id', 'sale_id']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->foreignId('spare_part_id')->nullable()->constrained('spare_parts');
            $table->foreignId('maintenance_service_id')->nullable()->constrained('maintenance_services');
            $table->foreignId('maintenance_part_id')->nullable()->constrained('maintenance_parts');
            $table->foreignId('bike_for_sale_id')->nullable()->constrained('bike_for_sale');
            $table->decimal('selling_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->integer('qty')->default(1);
            $table->unsignedInteger('returned_qty')->default(0);
            $table->string('status')->default('active');
            $table->foreignId('replaced_from_sale_item_id')
                ->nullable()
                ->constrained('sale_items')
                ->nullOnDelete();
            $table->boolean('is_unstored')->default(false);
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('unstored_type', 32)->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('sale_id');
            $table->index('status');
            $table->index('replaced_from_sale_item_id');
        });

        Schema::create('sale_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->text('summary');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->decimal('amount_delta', 14, 2)->default(0);
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->decimal('extra_amount_due', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sale_id', 'action_type']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('full_address');
            $table->string('city');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['sale_id', 'customer_id']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('customer_bike_id')->constrained('customer_bikes');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'partial', 'closed']);
            $table->text('notes')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('payment_method', 64)->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->uuid('public_token')->nullable()->unique();
            $table->timestamp('tracking_link_sent_at')->nullable();
            $table->unsignedInteger('tracking_link_send_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'customer_id', 'user_id']);
        });

        Schema::create('ticket_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->string('name');
            $table->enum('status', ['pending', 'completed']);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ticket_id', 'status']);
        });

        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('ticket_tasks');
            $table->foreignId('ticket_id')->constrained('tickets');
            $table->foreignId('spare_part_id')->nullable()->constrained('spare_parts');
            $table->foreignId('maintenance_service_id')->nullable()->constrained('maintenance_services');
            $table->foreignId('maintenance_part_id')->nullable()->constrained('maintenance_parts');
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->decimal('price_snapshot', 14, 2);
            $table->decimal('discount', 14, 2)->default(0);
            $table->integer('qty')->default(1);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->boolean('is_unstored')->default(false);
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->string('unstored_type', 32)->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ticket_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_items');
        Schema::dropIfExists('ticket_tasks');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('deliveries');
        Schema::dropIfExists('sale_adjustments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('customer_sale');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('bike_blueprint_maintenance_parts');
        Schema::dropIfExists('bike_blueprint_products');
        Schema::dropIfExists('bike_blueprint_spare_parts');
        Schema::dropIfExists('customer_bikes');
        Schema::dropIfExists('bike_for_sale');
        Schema::dropIfExists('maintenance_parts');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('products');
        Schema::dropIfExists('maintenance_services');
        Schema::dropIfExists('bike_blueprints');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('maintenance_service_sectors');
        Schema::dropIfExists('maintenance_part_categories');
        Schema::dropIfExists('spare_part_categories');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('sellers');
    }
};
