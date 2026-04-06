<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddPaymentService
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

            if ($sale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'Cannot register a payment for a sale without items.',
                ]);
            }

            $summary = $this->syncSaleTotalsService->calculate($sale);
            $status = $data['status'] ?? Payment::STATUS_COMPLETED;
            $amount = round((float) $data['amount'], 2);

            if ($status === Payment::STATUS_COMPLETED && $amount > $summary['remaining_amount']) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Payment amount exceeds the remaining balance of %.2f.', $summary['remaining_amount']),
                ]);
            }

            Payment::query()->create([
                'sale_id' => $sale->id,
                'amount' => $amount,
                'method' => $data['method'],
                'status' => $status,
            ]);

            $sale->load('payments');

            return $this->syncSaleTotalsService->sync($sale);
        });
    }
}
