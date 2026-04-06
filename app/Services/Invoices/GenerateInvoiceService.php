<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Support\Str;

class GenerateInvoiceService
{
    public function forSale(Sale $sale): Invoice
    {
        $sale->loadMissing('items', 'payments');

        $status = $this->resolveSaleStatus($sale);

        return Invoice::query()->updateOrCreate(
            [
                'type' => Invoice::TYPE_SALE,
                'reference_id' => $sale->id,
            ],
            [
                'invoice_number' => $this->generateInvoiceNumber(
                    Invoice::query()
                        ->where('type', Invoice::TYPE_SALE)
                        ->where('reference_id', $sale->id)
                        ->value('invoice_number')
                ),
                'total' => round((float) $sale->items->sum(fn ($item) => $item->price_snapshot * $item->qty), 2),
                'discount' => round((float) $sale->items->sum('discount'), 2),
                'final_total' => round((float) $sale->final_amount, 2),
                'status' => $status,
            ]
        );
    }

    public function forTicket(Ticket $ticket): Invoice
    {
        $ticket->loadMissing('items');

        $total = round((float) $ticket->items
            ->where('item_type', TicketItem::ITEM_TYPE_SERVICE)
            ->sum(fn ($item) => $item->price_snapshot * $item->qty), 2);

        return Invoice::query()->updateOrCreate(
            [
                'type' => Invoice::TYPE_TICKET,
                'reference_id' => $ticket->id,
            ],
            [
                'invoice_number' => $this->generateInvoiceNumber(
                    Invoice::query()
                        ->where('type', Invoice::TYPE_TICKET)
                        ->where('reference_id', $ticket->id)
                        ->value('invoice_number')
                ),
                'total' => $total,
                'discount' => 0,
                'final_total' => $total,
                'status' => Invoice::STATUS_UNPAID,
            ]
        );
    }

    private function resolveSaleStatus(Sale $sale): string
    {
        $paid = round((float) $sale->payments
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount'), 2);

        if ($paid <= 0) {
            return Invoice::STATUS_UNPAID;
        }

        if ($paid < (float) $sale->final_amount) {
            return Invoice::STATUS_PARTIAL;
        }

        return Invoice::STATUS_PAID;
    }

    private function generateInvoiceNumber(?string $existingNumber = null): string
    {
        return $existingNumber ?? 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
