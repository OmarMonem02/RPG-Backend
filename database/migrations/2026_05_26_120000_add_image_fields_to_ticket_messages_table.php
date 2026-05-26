<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('image_url', 2048)->nullable()->after('body');
            $table->string('image_public_id', 512)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'image_public_id']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
