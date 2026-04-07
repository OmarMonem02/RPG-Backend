<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Sale;
use App\Services\Invoices\GenerateInvoiceService;
use App\Services\Sales\SyncSaleTotalsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePaymentService
{
    public function __construct(
        private readonly SyncSaleTotalsService $syncSaleTotalsService,
        private readonly GenerateInvoiceService $generateInvoiceService,
    ) {}

    public function execute(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer', 'seller'])
                ->findOrFail($payment->sale_id);

            $amount = round((float) ($data['amount'] ?? $payment->amount), 2);
            $status = $data['status'] ?? $payment->status;

            $completedPaidElsewhere = round((float) $sale->payments
                ->where('id', '!=', $payment->id)
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount'), 2);

            $allowedBalance = max(round((float) $sale->final_amount - $completedPaidElsewhere, 2), 0);

            if ($status === Payment::STATUS_COMPLETED && $amount > $allowedBalance) {
                throw ValidationException::withMessages([
                    'amount' => sprintf('Payment amount exceeds the remaining balance of %.2f.', $allowedBalance),
                ]);
            }

            $payment->fill([
                'amount' => $amount,
                'method' => $data['method'] ?? $payment->method,
                'status' => $status,
            ])->save();

            $sale->load('payments');
            $sale = $this->syncSaleTotalsService->sync($sale);
            $this->generateInvoiceService->forSale($sale);

            return $payment->refresh();
        });
    }
}
