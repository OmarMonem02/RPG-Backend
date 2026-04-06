<?php

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\Sale;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;

class ListInvoicesService
{
    public function execute(array $filters = []): Collection
    {
        return Invoice::query()
            ->when(isset($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when(isset($filters['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->latest('id')
            ->get()
            ->map(fn (Invoice $invoice) => $this->decorate($invoice));
    }

    public function show(Invoice $invoice): array
    {
        return $this->decorate($invoice);
    }

    private function decorate(Invoice $invoice): array
    {
        return array_merge($invoice->toArray(), [
            'reference' => $this->resolveReference($invoice),
        ]);
    }

    private function resolveReference(Invoice $invoice): ?array
    {
        if ($invoice->type === Invoice::TYPE_SALE) {
            $sale = Sale::query()
                ->withTrashed()
                ->with(['customer', 'seller'])
                ->find($invoice->reference_id);

            return $sale?->toArray();
        }

        if ($invoice->type === Invoice::TYPE_TICKET) {
            $ticket = Ticket::query()
                ->withTrashed()
                ->with(['customer', 'customerBike'])
                ->find($invoice->reference_id);

            return $ticket?->toArray();
        }

        return null;
    }
}
