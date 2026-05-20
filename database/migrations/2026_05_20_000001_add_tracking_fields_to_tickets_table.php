<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique()->after('total');
            $table->timestamp('tracking_link_sent_at')->nullable()->after('public_token');
            $table->unsignedInteger('tracking_link_send_count')->default(0)->after('tracking_link_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['public_token', 'tracking_link_sent_at', 'tracking_link_send_count']);
        });
    }
};
