<?php

use App\Models\BikeForSale;
use App\Models\Product;
use App\Models\SparePart;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');
            $table->string('url');
            $table->string('public_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['imageable_type', 'imageable_id', 'is_primary']);
        });

        $this->backfillExistingImages(Product::class, 'products');
        $this->backfillExistingImages(SparePart::class, 'spare_parts');
        $this->backfillExistingImages(BikeForSale::class, 'bike_for_sale');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_images');
    }

    private function backfillExistingImages(string $modelClass, string $table): void
    {
        $rows = DB::table($table)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->get(['id', 'image', 'image_public_id']);

        $now = now();

        foreach ($rows as $row) {
            DB::table('inventory_images')->insert([
                'imageable_type' => $modelClass,
                'imageable_id' => $row->id,
                'url' => $row->image,
                'public_id' => $row->image_public_id,
                'is_primary' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
