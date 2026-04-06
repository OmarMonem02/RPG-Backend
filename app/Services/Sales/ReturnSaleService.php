<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnSaleService
{
    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer', 'seller'])
                ->findOrFail($sale->id);

            if ($sale->trashed()) {
                throw ValidationException::withMessages([
                    'sale' => 'Sale has already been returned.',
                ]);
            }

            foreach ($sale->items as $item) {
                if ($item->item_type !== SaleItem::ITEM_TYPE_PRODUCT) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($item->item_id);

                if ($product !== null) {
                    $product->increment('qty', $item->qty);
                }
            }

            $sale->payments()
                ->where('status', '!=', Payment::STATUS_REFUNDED)
                ->update(['status' => Payment::STATUS_REFUNDED]);

            $sale->delete();

            return $sale->load(['customer', 'seller', 'items', 'payments']);
        });
    }
}
