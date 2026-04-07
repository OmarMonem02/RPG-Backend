<?php

namespace App\Services\Returns;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockLog;
use App\Services\Inventory\AdjustStockService;
use App\Services\Invoices\GenerateInvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HandleReturnService
{
    public function __construct(
        private readonly AdjustStockService $adjustStockService,
        private readonly GenerateInvoiceService $generateInvoiceService,
    ) {}

    public function execute(Sale $sale, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data): SaleReturn {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items.returns', 'returns', 'payments'])
                ->findOrFail($sale->id);

            if ($sale->trashed()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cannot return items from a deleted sale.',
                ]);
            }

            $item = SaleItem::query()
                ->where('sale_id', $sale->id)
                ->with('returns')
                ->findOrFail($data['item_id']);

            $alreadyReturned = (int) $item->returns->sum('qty');
            $remainingQty = $item->qty - $alreadyReturned;

            if ($remainingQty <= 0) {
                throw ValidationException::withMessages([
                    'item_id' => 'This sale item has already been fully returned.',
                ]);
            }

            if ((int) $data['qty'] > $remainingQty) {
                throw ValidationException::withMessages([
                    'qty' => 'Return quantity exceeds the remaining sold quantity.',
                ]);
            }

            if ($item->item_type === SaleItem::ITEM_TYPE_BIKE && (int) $data['qty'] !== 1) {
                throw ValidationException::withMessages([
                    'qty' => 'Bike returns must be processed with quantity 1.',
                ]);
            }

            if ($item->item_type === SaleItem::ITEM_TYPE_PRODUCT) {
                $product = Product::query()->find($item->item_id);

                if ($product !== null) {
                    $this->adjustStockService->execute(
                        $product,
                        (float) $data['qty'],
                        StockLog::CHANGE_TYPE_RETURN,
                        'sale_return',
                        $sale->id
                    );
                }
            }

            $return = SaleReturn::query()->create([
                'sale_id' => $sale->id,
                'item_id' => $item->id,
                'qty' => $data['qty'],
                'reason' => $data['reason'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->syncSaleFinancials($sale);

            return $return->load(['sale', 'item', 'creator']);
        });
    }

    public function syncSaleFinancials(Sale $sale): void
    {
        $sale->loadMissing(['items.returns', 'payments']);

        $returnedValue = $sale->items->sum(function (SaleItem $item): float {
            $returnedQty = (int) $item->returns->sum('qty');

            if ($returnedQty <= 0 || $item->qty <= 0) {
                return 0;
            }

            $unitDiscount = round((float) $item->discount / $item->qty, 2);

            return round(((float) $item->price_snapshot * $returnedQty) - ($unitDiscount * $returnedQty), 2);
        });

        $finalTotal = max(round((float) $sale->final_amount - $returnedValue, 2), 0);
        $paid = round((float) $sale->payments->where('status', 'completed')->sum('amount'), 2);
        $status = $paid <= 0
            ? Invoice::STATUS_UNPAID
            : ($paid < $finalTotal ? Invoice::STATUS_PARTIAL : Invoice::STATUS_PAID);

        $saleStatus = $paid <= 0
            ? Sale::STATUS_PENDING
            : ($paid < $finalTotal ? Sale::STATUS_PARTIAL : Sale::STATUS_COMPLETED);

        $sale->update([
            'status' => $finalTotal <= 0 ? Sale::STATUS_COMPLETED : $saleStatus,
        ]);

        Invoice::query()
            ->where('type', Invoice::TYPE_SALE)
            ->where('reference_id', $sale->id)
            ->update([
                'final_total' => $finalTotal,
                'status' => $status,
            ]);
    }
}
