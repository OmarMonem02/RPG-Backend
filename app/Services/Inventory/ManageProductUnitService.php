<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\DB;

class ManageProductUnitService
{
    public function create(Product $product, array $data): ProductUnit
    {
        return DB::transaction(function () use ($product, $data): ProductUnit {
            return $product->units()->create($data);
        });
    }

    public function update(Product $product, ProductUnit $unit, array $data): ProductUnit
    {
        return DB::transaction(function () use ($product, $unit, $data): ProductUnit {
            $unit = ProductUnit::query()->where('product_id', $product->id)->findOrFail($unit->id);
            $unit->update($data);

            return $unit->fresh();
        });
    }

    public function delete(Product $product, ProductUnit $unit): void
    {
        DB::transaction(function () use ($product, $unit): void {
            ProductUnit::query()->where('product_id', $product->id)->findOrFail($unit->id)->delete();
        });
    }
}
