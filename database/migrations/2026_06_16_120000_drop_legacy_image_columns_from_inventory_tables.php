<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_public_id']);
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_public_id']);
        });

        Schema::table('bike_for_sale', function (Blueprint $table) {
            $table->dropColumn(['image', 'image_public_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image')->nullable()->after('sku');
            $table->string('image_public_id')->nullable()->after('image');
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->string('image')->nullable()->after('sku');
            $table->string('image_public_id')->nullable()->after('image');
        });

        Schema::table('bike_for_sale', function (Blueprint $table) {
            $table->string('image')->nullable()->after('id');
            $table->string('image_public_id')->nullable()->after('image');
        });
    }
};
