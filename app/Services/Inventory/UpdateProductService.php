<?php

namespace App\Services\Inventory;

use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateProductService
{
    public function __construct(
        private readonly CalculateProductPriceService $calculateProductPriceService,
        private readonly AssignProductToBikeService $assignProductToBikeService,
    ) {}

    public function execute(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product = Product::query()->with('units', 'bikes')->lockForUpdate()->findOrFail($product->id);
            $oldSellingPrice = (float) $product->selling_price;

            $product->fill($data);
            $product->save();

            if (array_key_exists('units', $data)) {
                $existingIds = [];

                foreach ($data['units'] as $unitData) {
                    $unit = isset($unitData['id'])
                        ? ProductUnit::query()->where('product_id', $product->id)->findOrFail($unitData['id'])
                        : new ProductUnit(['product_id' => $product->id]);

                    $unit->fill([
                        'unit_name' => $unitData['unit_name'],
                        'conversion_factor' => $unitData['conversion_factor'],
                        'price' => $unitData['price'],
                    ]);

                    $product->units()->save($unit);
                    $existingIds[] = $unit->id;
                }

                $product->units()->whereNotIn('id', $existingIds)->delete();
            }

            if (array_key_exists('bike_ids', $data)) {
                $product = $this->assignProductToBikeService->execute($product, $data['bike_ids']);
            }

            if ($product->cost_price_usd !== null && array_key_exists('cost_price_usd', $data)) {
                $product->update([
                    'cost_price' => $this->calculateProductPriceService->execute($product)['cost_price_egp'],
                ]);
            }

            if ($oldSellingPrice !== (float) $product->selling_price) {
                PriceHistory::query()->create([
                    'product_id' => $product->id,
                    'old_price' => $oldSellingPrice,
                    'new_price' => $product->selling_price,
                    'changed_by' => Auth::id(),
                ]);
            }

            return $product->fresh(['units', 'bikes', 'category', 'brand', 'stockLogs', 'priceHistories']);
        });
    }
}
