<?php

namespace App\Services\Sales;

use App\Models\BikeInventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddItemToSaleService
{
    public function __construct(
        private readonly SyncSaleTotalsService $syncSaleTotalsService,
    ) {
    }

    public function execute(Sale $sale, array $data): Sale
    {
        return DB::transaction(function () use ($sale, $data): Sale {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer', 'seller'])
                ->findOrFail($sale->id);

            if ($sale->trashed()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cannot modify a returned sale.',
                ]);
            }

            match ($data['item_type']) {
                SaleItem::ITEM_TYPE_PRODUCT => $this->addProductItem($sale, $data),
                SaleItem::ITEM_TYPE_BIKE => $this->addBikeItem($sale, $data),
                default => throw ValidationException::withMessages([
                    'item_type' => 'Unsupported sale item type.',
                ]),
            };

            return $this->syncSaleTotalsService->sync($sale);
        });
    }

    private function addProductItem(Sale $sale, array $data): void
    {
        $product = Product::query()->lockForUpdate()->findOrFail($data['item_id']);
        $qty = (int) $data['qty'];
        $discount = round((float) ($data['discount'] ?? 0), 2);

        if ($product->qty < $qty) {
            throw ValidationException::withMessages([
                'qty' => 'Requested quantity exceeds available stock.',
            ]);
        }

        $maxDiscount = $this->resolveProductMaxDiscount($product, $qty);

        if ($discount > $maxDiscount) {
            throw ValidationException::withMessages([
                'discount' => sprintf('Discount exceeds the maximum allowed amount of %.2f.', $maxDiscount),
            ]);
        }

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'item_type' => SaleItem::ITEM_TYPE_PRODUCT,
            'item_id' => $product->id,
            'qty' => $qty,
            'price_snapshot' => $product->selling_price,
            'discount' => $discount,
        ]);

        $product->decrement('qty', $qty);
    }

    private function addBikeItem(Sale $sale, array $data): void
    {
        $bike = BikeInventory::query()->lockForUpdate()->findOrFail($data['item_id']);
        $discount = round((float) ($data['discount'] ?? 0), 2);

        $isReserved = SaleItem::query()
            ->where('item_type', SaleItem::ITEM_TYPE_BIKE)
            ->where('item_id', $bike->id)
            ->whereHas('sale', fn ($query) => $query->whereNull('deleted_at'))
            ->exists();

        if ($isReserved) {
            throw ValidationException::withMessages([
                'item_id' => 'Selected bike is already attached to another active sale.',
            ]);
        }

        if ($discount > (float) $bike->selling_price) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot exceed bike selling price.',
            ]);
        }

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'item_type' => SaleItem::ITEM_TYPE_BIKE,
            'item_id' => $bike->id,
            'qty' => 1,
            'price_snapshot' => $bike->selling_price,
            'discount' => $discount,
        ]);
    }

    private function resolveProductMaxDiscount(Product $product, int $qty): float
    {
        if ($product->max_discount_type === Product::DISCOUNT_TYPE_PERCENTAGE) {
            return round(($product->selling_price * $qty) * ((float) $product->max_discount_value / 100), 2);
        }

        return round((float) $product->max_discount_value * $qty, 2);
    }
}
