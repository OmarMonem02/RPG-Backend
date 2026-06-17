<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('full_address');
            $table->string('city');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_default']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('customer_address_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_addresses')
                ->nullOnDelete();
        });

        $customers = DB::table('customers')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->get(['id', 'address']);

        foreach ($customers as $customer) {
            DB::table('customer_addresses')->insert([
                'customer_id' => $customer->id,
                'label' => null,
                'full_address' => $customer->address,
                'city' => '—',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_address_id');
        });

        Schema::dropIfExists('customer_addresses');
    }
};
