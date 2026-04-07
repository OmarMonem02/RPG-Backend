<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Sale;
use App\Services\Invoices\GenerateInvoiceService;
use App\Services\Sales\SyncSaleTotalsService;
use Illuminate\Support\Facades\DB;

class DeletePaymentService
{
    public function __construct(
        private readonly SyncSaleTotalsService $syncSaleTotalsService,
        private readonly GenerateInvoiceService $generateInvoiceService,
    ) {}

    public function execute(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $sale = Sale::query()
                ->lockForUpdate()
                ->with(['items', 'payments', 'customer', 'seller'])
                ->findOrFail($payment->sale_id);

            $payment->delete();

            $sale->load('payments');
            $sale = $this->syncSaleTotalsService->sync($sale);
            $this->generateInvoiceService->forSale($sale);
        });
    }
}
