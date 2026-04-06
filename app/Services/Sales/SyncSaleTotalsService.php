<?php

namespace App\Services\Sales;

use App\Models\Payment;
use App\Models\Sale;

class SyncSaleTotalsService
{
    public function calculate(Sale $sale): array
    {
        $sale->loadMissing('items', 'payments');

        $total = round((float) $sale->items->sum(fn ($item) => $item->price_snapshot * $item->qty), 2);
        $discount = round((float) $sale->items->sum('discount'), 2);
        $finalAmount = max(round($total - $discount, 2), 0);
        $paidAmount = round((float) $sale->payments
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount'), 2);

        return [
            'total' => $total,
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => max(round($finalAmount - $paidAmount, 2), 0),
            'status' => $this->resolveStatus($finalAmount, $paidAmount),
        ];
    }

    public function sync(Sale $sale): Sale
    {
        $summary = $this->calculate($sale);

        $sale->fill([
            'total' => $summary['total'],
            'discount' => $summary['discount'],
            'status' => $summary['status'],
        ])->save();

        $sale->setRelation('payments', $sale->payments);
        $sale->setRelation('items', $sale->items);

        return $sale->refresh()->load(['customer', 'seller', 'items', 'payments']);
    }

    private function resolveStatus(float $finalAmount, float $paidAmount): string
    {
        if ($finalAmount <= 0 || $paidAmount <= 0) {
            return Sale::STATUS_PENDING;
        }

        if ($paidAmount < $finalAmount) {
            return Sale::STATUS_PARTIAL;
        }

        return Sale::STATUS_COMPLETED;
    }
}
