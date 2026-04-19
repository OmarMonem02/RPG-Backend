<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\MaintenanceService;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SaleInventoryService
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const SELLABLE_MODELS = [
        'product' => Product::class,
        'spare_part' => SparePart::class,
        'maintenance_service' => MaintenanceService::class,
        'bike' => BikeForSale::class,
    ];

    /**
     * @var array<string, string>
     */
    private const ITEM_TYPE_COLUMNS = [
        'product' => 'product_id',
        'spare_part' => 'spare_part_id',
        'maintenance_service' => 'maintenance_service_id',
        'bike' => 'bike_for_sale_id',
    ];

    public function createSaleItem(Sale $sale, array $itemData, ?int $replacedFromSaleItemId = null): SaleItem
    {
        $this->validateSingleReference($itemData);
        $this->assertQuantityRulesForPayload($itemData);
        $this->deductInventoryForPayload($itemData);

        return $sale->items()->create([
            'product_id' => $itemData['product_id'] ?? null,
            'spare_part_id' => $itemData['spare_part_id'] ?? null,
            'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
            'bike_for_sale_id' => $itemData['bike_for_sale_id'] ?? null,
            'selling_price' => $itemData['selling_price'],
            'discount' => $itemData['discount'] ?? 0,
            'qty' => $itemData['qty'],
            'returned_qty' => 0,
            'status' => SaleItem::STATUS_ACTIVE,
            'replaced_from_sale_item_id' => $replacedFromSaleItemId,
        ]);
    }

    public function lineSubtotal(SaleItem $saleItem, int $qty): float
    {
        return ((float) $saleItem->selling_price - (float) $saleItem->discount) * $qty;
    }

    public function assertQuantityRulesForExistingItem(SaleItem $saleItem, int $qty): void
    {
        if ($saleItem->bike_for_sale_id && $qty !== 1) {
            throw ValidationException::withMessages([
                'qty' => ['Bike sale items must use a quantity of 1.'],
            ]);
        }
    }

    public function assertReturnableQuantity(SaleItem $saleItem, int $qty): void
    {
        if ($qty > $saleItem->remainingQty()) {
            throw ValidationException::withMessages([
                'qty' => ['Return or exchange quantity exceeds the remaining quantity for this sale item.'],
            ]);
        }
    }

    public function restoreInventoryForSaleItem(SaleItem $saleItem, int $qty): void
    {
        [$type, $model] = $this->resolveSellableFromSaleItem($saleItem);

        match ($type) {
            'product', 'spare_part' => $model->increment('stock_quantity', $qty),
            'bike' => $this->markBikeAsAvailable($model, $qty),
            default => null,
        };
    }

    public function syncInventoryForQtyChange(SaleItem $saleItem, int $deltaQty): void
    {
        if ($deltaQty === 0) {
            return;
        }

        [$type, $model] = $this->resolveSellableFromSaleItem($saleItem);

        if ($deltaQty > 0) {
            match ($type) {
                'product', 'spare_part' => $this->deductStockQuantity($model, $deltaQty),
                'bike' => $this->markBikeAsSold($model, $deltaQty),
                default => null,
            };

            return;
        }

        $restoreQty = abs($deltaQty);
        match ($type) {
            'product', 'spare_part' => $model->increment('stock_quantity', $restoreQty),
            'bike' => $this->markBikeAsAvailable($model, $restoreQty),
            default => null,
        };
    }

    public function describeSaleItem(SaleItem $saleItem): string
    {
        $saleItem->loadMissing('product', 'sparePart', 'maintenanceService', 'bikeForSale.bikeBlueprint.brand');

        return match (true) {
            ! is_null($saleItem->product) => "product {$saleItem->product->name}",
            ! is_null($saleItem->sparePart) => "spare part {$saleItem->sparePart->name}",
            ! is_null($saleItem->maintenanceService) => "maintenance service {$saleItem->maintenanceService->name}",
            ! is_null($saleItem->bikeForSale) => 'bike ' . trim(($saleItem->bikeForSale->bikeBlueprint?->brand?->name ?? '') . ' ' . ($saleItem->bikeForSale->bikeBlueprint?->model ?? $saleItem->bikeForSale->vin)),
            default => 'unknown item',
        };
    }

    /**
     * @return array{0: string, 1: Model}
     */
    public function resolveSellableFromSaleItem(SaleItem $saleItem): array
    {
        return match (true) {
            ! is_null($saleItem->product_id) => ['product', Product::query()->lockForUpdate()->findOrFail($saleItem->product_id)],
            ! is_null($saleItem->spare_part_id) => ['spare_part', SparePart::query()->lockForUpdate()->findOrFail($saleItem->spare_part_id)],
            ! is_null($saleItem->maintenance_service_id) => ['maintenance_service', MaintenanceService::query()->lockForUpdate()->findOrFail($saleItem->maintenance_service_id)],
            ! is_null($saleItem->bike_for_sale_id) => ['bike', BikeForSale::query()->lockForUpdate()->findOrFail($saleItem->bike_for_sale_id)],
            default => throw ValidationException::withMessages([
                'sale_item_id' => ['The sale item is missing its referenced sellable entity.'],
            ]),
        };
    }

    private function validateSingleReference(array $itemData): void
    {
        $count = collect(array_values(self::ITEM_TYPE_COLUMNS))
            ->filter(fn (string $column) => ! empty($itemData[$column]))
            ->count();

        if ($count !== 1) {
            throw ValidationException::withMessages([
                'item_type' => ['Exactly one sellable item reference must be provided.'],
            ]);
        }
    }

    private function assertQuantityRulesForPayload(array $itemData): void
    {
        if (! empty($itemData['bike_for_sale_id']) && (int) $itemData['qty'] !== 1) {
            throw ValidationException::withMessages([
                'qty' => ['Bike sale items must use a quantity of 1.'],
            ]);
        }
    }

    private function deductInventoryForPayload(array $itemData): void
    {
        [$type, $model] = $this->resolveSellableFromPayload($itemData);
        $qty = (int) $itemData['qty'];

        match ($type) {
            'product', 'spare_part' => $this->deductStockQuantity($model, $qty),
            'bike' => $this->markBikeAsSold($model, $qty),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: Model}
     */
    private function resolveSellableFromPayload(array $itemData): array
    {
        foreach (self::ITEM_TYPE_COLUMNS as $type => $column) {
            if (! empty($itemData[$column])) {
                $modelClass = self::SELLABLE_MODELS[$type];
                return [$type, $modelClass::query()->lockForUpdate()->findOrFail($itemData[$column])];
            }
        }

        throw ValidationException::withMessages([
            'item_type' => ['No valid sellable item reference was found.'],
        ]);
    }

    private function deductStockQuantity(Model $model, int $qty): void
    {
        if ((int) $model->stock_quantity < $qty) {
            throw ValidationException::withMessages([
                'qty' => ["Insufficient stock for {$model->name}."],
            ]);
        }

        $model->decrement('stock_quantity', $qty);
    }

    private function markBikeAsSold(Model $model, int $qty): void
    {
        if ($qty !== 1) {
            throw ValidationException::withMessages([
                'qty' => ['Bike sale items must use a quantity of 1.'],
            ]);
        }

        if ($model->status !== 'available') {
            throw ValidationException::withMessages([
                'bike_for_sale_id' => ['Selected bike is not available for sale.'],
            ]);
        }

        $model->update(['status' => 'sold']);
    }

    private function markBikeAsAvailable(Model $model, int $qty): void
    {
        if ($qty !== 1) {
            throw ValidationException::withMessages([
                'qty' => ['Bike sale items must use a quantity of 1.'],
            ]);
        }

        $model->update(['status' => 'available']);
    }
}
