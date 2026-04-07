<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('type');
        });

        DB::statement('DELETE FROM categories');

        Schema::table('categories', function (Blueprint $table): void {
            $table->unique(['name', 'type']);
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('address');
        });

        DB::statement('DELETE FROM customers');

        Schema::table('customers', function (Blueprint $table): void {
            $table->unique('phone');
        });

        Schema::table('customer_bikes', function (Blueprint $table): void {
            $table->text('modifications')->nullable()->after('year');
        });

        DB::statement('DELETE FROM customer_bikes');

        Schema::table('customer_bikes', function (Blueprint $table): void {
            $table->unique(['customer_id', 'brand', 'model', 'year']);
        });

        Schema::table('bikes_inventory', function (Blueprint $table): void {
            $table->foreignId('bike_id')->nullable()->after('id')->constrained('bikes')->nullOnDelete();
            $table->integer('mileage')->nullable()->after('selling_price');
            $table->integer('cc')->nullable()->after('mileage');
            $table->integer('horse_power')->nullable()->after('cc');
            $table->text('notes')->nullable()->after('owner_phone');
            $table->string('brand')->nullable()->change();
            $table->string('model')->nullable()->change();
            $table->year('year')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bikes_inventory', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bike_id');
            $table->dropColumn(['mileage', 'cc', 'horse_power', 'notes']);
            $table->string('brand')->nullable(false)->change();
            $table->string('model')->nullable(false)->change();
            $table->year('year')->nullable(false)->change();
        });

        Schema::table('customer_bikes', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'brand', 'model', 'year']);
            $table->dropColumn('modifications');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn('notes');
        });

        Schema::table('brands', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['name', 'type']);
            $table->dropColumn('description');
        });
    }
};
