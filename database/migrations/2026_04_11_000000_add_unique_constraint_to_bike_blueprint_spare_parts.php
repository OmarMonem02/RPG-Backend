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
        Schema::table('bike_blueprint_spare_parts', function (Blueprint $table) {
            $table->unique(['bike_blueprint_id', 'spare_part_id'], 'bike_blueprint_spare_parts_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bike_blueprint_spare_parts', function (Blueprint $table) {
            $table->dropUnique('bike_blueprint_spare_parts_unique');
        });
    }
};
