<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('settings', function (Blueprint $table) {
                $table->decimal('value')->nullable()->change();
            });

            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('value')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->integer('value')->change();
        });
    }
};
