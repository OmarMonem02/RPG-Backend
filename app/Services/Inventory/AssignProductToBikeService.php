<?php

namespace App\Services\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class AssignProductToBikeService
{
    public function execute(Product $product, array $bikeIds): Product
    {
        return DB::transaction(function () use ($product, $bikeIds): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $product->bikes()->sync($bikeIds);

            if ($bikeIds !== []) {
                $product->update(['is_universal' => false]);
            }

            return $product->fresh(['units', 'bikes', 'stockLogs', 'priceHistories']);
        });
    }
}
