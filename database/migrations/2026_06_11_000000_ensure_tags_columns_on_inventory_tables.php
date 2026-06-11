<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety net for environments where the original tags migration did not run
 * (e.g. deploy used migrate --graceful and the after('notes') clause failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'tags')) {
            Schema::table('products', function (Blueprint $table) {
                $table->json('tags')->nullable();
            });
        }

        if (! Schema::hasColumn('spare_parts', 'tags')) {
            Schema::table('spare_parts', function (Blueprint $table) {
                $table->json('tags')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-op: do not drop columns that may have been created by the original migration.
    }
};
