<?php

namespace App\Services\Inventory;

use App\Models\PriceHistory;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkUpdateProductsService
{
    public function __construct(
        private readonly CalculateProductPriceService $calculateProductPriceService,
    ) {}

    public function execute(array $productIds, array $attributes): int
    {
        return DB::transaction(function () use ($productIds, $attributes): int {
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get();

            foreach ($products as $product) {
                $type = $attributes['max_discount_type'] ?? $product->max_discount_type;
                $value = (float) ($attributes['max_discount_value'] ?? $product->max_discount_value);
                $sellingPrice = (float) ($attributes['selling_price'] ?? $product->selling_price);

                if ($type === Product::DISCOUNT_TYPE_PERCENTAGE && $value > 100) {
                    throw ValidationException::withMessages([
                        'attributes.max_discount_value' => 'Percentage discount cannot exceed 100.',
                    ]);
                }

                if ($type === Product::DISCOUNT_TYPE_FIXED && $value > $sellingPrice) {
                    throw ValidationException::withMessages([
                        'attributes.max_discount_value' => 'Fixed discount cannot exceed the selling price.',
                    ]);
                }

                $oldPrice = (float) $product->selling_price;
                $product->fill($attributes);
                $product->save();

                if (array_key_exists('cost_price_usd', $attributes) && ! array_key_exists('cost_price', $attributes)) {
                    $product->update([
                        'cost_price' => $this->calculateProductPriceService->execute($product)['cost_price_egp'],
                    ]);
                }

                if (array_key_exists('selling_price', $attributes) && $oldPrice !== (float) $product->selling_price) {
                    PriceHistory::query()->create([
                        'product_id' => $product->id,
                        'old_price' => $oldPrice,
                        'new_price' => $product->selling_price,
                        'changed_by' => Auth::id(),
                    ]);
                }
            }

            return $products->count();
        });
    }
}
