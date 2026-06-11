<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'tags')) {
            Schema::table('products', function (Blueprint $table) {
                $table->longText('tags')->nullable();
            });
        }

        if (! Schema::hasColumn('spare_parts', 'tags')) {
            Schema::table('spare_parts', function (Blueprint $table) {
                $table->longText('tags')->nullable();
            });
        }
    }

    public function down(): void
    {
        // No-op: never drop tags columns added by safety migrations.
    }
};
