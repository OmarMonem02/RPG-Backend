<?php

namespace App\Services\Inventory;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateProductService
{
    public function __construct(
        private readonly CalculateProductPriceService $calculateProductPriceService,
        private readonly AssignProductToBikeService $assignProductToBikeService,
    ) {
    }

    public function execute(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::query()->create([
                'type' => $data['type'],
                'name' => $data['name'],
                'sku' => $data['sku'],
                'part_number' => $data['part_number'] ?? null,
                'category_id' => $data['category_id'],
                'brand_id' => $data['brand_id'],
                'qty' => $data['qty'],
                'cost_price' => $data['cost_price'] ?? 0,
                'selling_price' => $data['selling_price'],
                'cost_price_usd' => $data['cost_price_usd'] ?? null,
                'max_discount_type' => $data['max_discount_type'],
                'max_discount_value' => $data['max_discount_value'],
                'is_universal' => (bool) ($data['is_universal'] ?? false),
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['units'] ?? [] as $unit) {
                $product->units()->create([
                    'unit_name' => $unit['unit_name'],
                    'conversion_factor' => $unit['conversion_factor'],
                    'price' => $unit['price'],
                ]);
            }

            if (isset($data['bike_ids']) && $data['bike_ids'] !== []) {
                $product = $this->assignProductToBikeService->execute($product, $data['bike_ids']);
            }

            if ($product->cost_price_usd !== null && (float) $product->cost_price === 0.0) {
                $product->update([
                    'cost_price' => $this->calculateProductPriceService->execute($product)['cost_price_egp'],
                ]);
            }

            PriceHistory::query()->create([
                'product_id' => $product->id,
                'old_price' => $product->selling_price,
                'new_price' => $product->selling_price,
                'changed_by' => Auth::id(),
            ]);

            return $product->fresh(['units', 'bikes', 'stockLogs', 'priceHistories']);
        });
    }
}
