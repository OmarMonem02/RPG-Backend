<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductInventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availableQty = (int) $this->qty;
        $lowStockThreshold = Product::LOW_STOCK_THRESHOLD;
        $units = $this->resource->relationLoaded('units') ? $this->units : collect();
        $bikes = $this->resource->relationLoaded('bikes') ? $this->bikes : null;
        $compatibilityCount = $this->resource->bikes_count ?? ($bikes ? $bikes->count() : 0);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'sku' => $this->sku,
            'part_number' => $this->part_number,
            'description' => $this->description,
            'available_qty' => $availableQty,
            'stock' => [
                'available_qty' => $availableQty,
                'in_stock' => $availableQty > 0,
                'is_low_stock' => $availableQty > 0 && $availableQty <= $lowStockThreshold,
                'low_stock_threshold' => $lowStockThreshold,
            ],
            'pricing' => [
                'cost_price' => (float) $this->cost_price,
                'cost_price_usd' => $this->cost_price_usd !== null ? (float) $this->cost_price_usd : null,
                'selling_price' => (float) $this->selling_price,
                'unit_prices' => $units->map(fn ($unit): array => [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'conversion_factor' => (float) $unit->conversion_factor,
                    'price' => (float) $unit->price,
                ])->values()->all(),
            ],
            'discount_policy' => [
                'type' => $this->max_discount_type,
                'value' => (float) $this->max_discount_value,
                'max_discount_amount' => (float) $this->calculateMaxDiscount(),
            ],
            'category' => $this->whenLoaded('category', fn (): ?array => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'type' => $this->category->type,
            ] : null),
            'brand' => $this->whenLoaded('brand', fn (): ?array => $this->brand ? [
                'id' => $this->brand->id,
                'name' => $this->brand->name,
            ] : null),
            'compatibility' => [
                'is_universal' => (bool) $this->is_universal,
                'compatible_bikes_count' => $compatibilityCount,
                'bikes' => $bikes ? $bikes->map(fn ($bike): array => [
                    'id' => $bike->id,
                    'brand' => $bike->brand,
                    'model' => $bike->model,
                    'year' => $bike->year,
                ])->values()->all() : [],
            ],
            'has_units' => $units->isNotEmpty(),
            'is_sellable' => $availableQty > 0 && (float) $this->selling_price > 0,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
