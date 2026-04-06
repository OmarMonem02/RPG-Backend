<?php

namespace App\Services\Sales;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteSaleService
{
    public function __construct(
        private readonly SyncSaleTotalsService $syncSaleTotalsService,
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

            return $this->syncSaleTotalsService->sync($sale);
        });
    }
}
