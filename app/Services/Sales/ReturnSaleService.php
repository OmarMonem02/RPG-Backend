<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\Returns\HandleReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnSaleService
{
    public function __construct(
        private readonly HandleReturnService $handleReturnService,
    ) {
    }

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
                $alreadyReturned = (int) $item->returns()->sum('qty');
                $remainingQty = $item->qty - $alreadyReturned;

                if ($remainingQty > 0) {
                    $this->handleReturnService->execute($sale, [
                        'item_id' => $item->id,
                        'qty' => $remainingQty,
                        'reason' => 'Full sale return',
                    ]);
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
