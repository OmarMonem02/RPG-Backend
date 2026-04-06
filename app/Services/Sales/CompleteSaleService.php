<?php

namespace App\Services\Sales;

use App\Models\Sale;
use App\Services\Invoices\GenerateInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSaleService
{
    public function __construct(
        private readonly SyncSaleTotalsService $syncSaleTotalsService,
        private readonly GenerateInvoiceService $generateInvoiceService,
    ) {
    }

    public function execute(Sale $sale): Sale
    {
        return DB::transaction(function () use ($sale): Sale {
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer', 'seller'])
                ->findOrFail($sale->id);

            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cannot complete a sale without at least one item.',
                ]);
            }

            $sale = $this->syncSaleTotalsService->sync($sale);
            $this->generateInvoiceService->forSale($sale);

            return $sale;
        });
    }
}
