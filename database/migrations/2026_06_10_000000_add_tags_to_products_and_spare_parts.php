<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('notes');
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tags');
        });

        Schema::table('spare_parts', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
