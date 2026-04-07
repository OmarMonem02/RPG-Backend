<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdjustStockService
{
    public function __construct(
        private readonly ConvertUnitService $convertUnitService,
    ) {}

    public function execute(Product|int $product, float $quantity, string $changeType, string $referenceType, ?int $referenceId = null, ?int $unitId = null): Product
    {
        return DB::transaction(function () use ($product, $quantity, $changeType, $referenceType, $referenceId, $unitId): Product {
            $productId = $product instanceof Product ? $product->id : $product;
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            $unit = $unitId !== null ? ProductUnit::query()->where('product_id', $product->id)->findOrFail($unitId) : null;
            $baseQuantity = $this->convertUnitService->toBaseUnits($product, $quantity, $unit);

            if ($changeType === StockLog::CHANGE_TYPE_REDUCE && $product->qty < $baseQuantity) {
                throw ValidationException::withMessages([
                    'qty' => 'Insufficient stock for the requested adjustment.',
                ]);
            }

            $newQty = match ($changeType) {
                StockLog::CHANGE_TYPE_ADD, StockLog::CHANGE_TYPE_RETURN => $product->qty + $baseQuantity,
                StockLog::CHANGE_TYPE_REDUCE => $product->qty - $baseQuantity,
                default => throw ValidationException::withMessages([
                    'change_type' => 'Unsupported stock change type.',
                ]),
            };

            if ($newQty < 0) {
                throw ValidationException::withMessages([
                    'qty' => 'Stock cannot become negative.',
                ]);
            }

            $previousQty = (float) $product->qty;
            $product->update(['qty' => $newQty]);

            StockLog::query()->create([
                'product_id' => $product->id,
                'type' => $this->resolveLogType($referenceType),
                'change_type' => $changeType,
                'qty' => $baseQuantity,
                'qty_before' => $previousQty,
                'qty_after' => $newQty,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'user_id' => Auth::id(),
            ]);

            return $product->fresh(['units', 'bikes', 'stockLogs', 'priceHistories']);
        });
    }

    private function resolveLogType(string $referenceType): string
    {
        return match ($referenceType) {
            'sale' => 'sale',
            'return' => 'return',
            default => 'adjustment',
        };
    }
}
