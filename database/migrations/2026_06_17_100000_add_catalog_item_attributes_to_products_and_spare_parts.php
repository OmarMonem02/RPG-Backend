<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['products', 'spare_parts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('size', 100)->nullable()->after('part_number');
                $blueprint->string('color', 100)->nullable()->after('size');
                $blueprint->enum('item_status', ['new', 'used'])->default('new')->after('color');
            });
        }
    }

    public function down(): void
    {
        foreach (['products', 'spare_parts'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['size', 'color', 'item_status']);
            });
        }
    }
};
