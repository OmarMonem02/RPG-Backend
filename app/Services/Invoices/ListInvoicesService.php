<?php

namespace App\Services\Invoices;

use App\Models\BikeInventory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Support\Collection;

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

    public function items(Invoice $invoice): array
    {
        if ($invoice->type === Invoice::TYPE_SALE) {
            return $this->saleItems($invoice);
        }

        if ($invoice->type === Invoice::TYPE_TICKET) {
            return $this->ticketItems($invoice);
        }

        return [];
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

    private function saleItems(Invoice $invoice): array
    {
        $items = SaleItem::query()
            ->where('sale_id', $invoice->reference_id)
            ->latest('id')
            ->get();

        $productIds = $items
            ->where('item_type', SaleItem::ITEM_TYPE_PRODUCT)
            ->pluck('item_id')
            ->unique()
            ->values();

        $bikeIds = $items
            ->where('item_type', SaleItem::ITEM_TYPE_BIKE)
            ->pluck('item_id')
            ->unique()
            ->values();

        $productNames = Product::query()
            ->withTrashed()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $bikeNames = BikeInventory::query()
            ->whereIn('id', $bikeIds)
            ->get()
            ->mapWithKeys(fn (BikeInventory $bikeInventory) => [
                $bikeInventory->id => trim(sprintf('%s %s %s', (string) $bikeInventory->brand, (string) $bikeInventory->model, (string) $bikeInventory->year)),
            ]);

        return $items->map(function (SaleItem $item) use ($invoice, $productNames, $bikeNames): array {
            $name = $item->item_name;

            if ($name === null || $name === '') {
                $name = $item->item_type === SaleItem::ITEM_TYPE_PRODUCT
                    ? ($productNames[$item->item_id] ?? null)
                    : ($bikeNames[$item->item_id] ?? null);
            }

            return [
                'id' => $item->id,
                'invoice_id' => $invoice->id,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'item_name' => $name,
                'qty' => (int) $item->qty,
                'price_snapshot' => (float) $item->price_snapshot,
                'discount' => (float) $item->discount,
                'line_total' => (float) $item->line_total,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
            ];
        })->values()->all();
    }

    private function ticketItems(Invoice $invoice): array
    {
        $items = TicketItem::query()
            ->where('ticket_id', $invoice->reference_id)
            ->latest('id')
            ->get();

        $productIds = $items
            ->where('item_type', TicketItem::ITEM_TYPE_PRODUCT)
            ->pluck('item_id')
            ->unique()
            ->values();

        $serviceIds = $items
            ->where('item_type', TicketItem::ITEM_TYPE_SERVICE)
            ->pluck('item_id')
            ->unique()
            ->values();

        $productNames = Product::query()
            ->withTrashed()
            ->whereIn('id', $productIds)
            ->pluck('name', 'id');

        $serviceNames = Service::query()
            ->withTrashed()
            ->whereIn('id', $serviceIds)
            ->pluck('name', 'id');

        return $items->map(function (TicketItem $item) use ($invoice, $productNames, $serviceNames): array {
            $name = $item->item_type === TicketItem::ITEM_TYPE_PRODUCT
                ? ($productNames[$item->item_id] ?? null)
                : ($serviceNames[$item->item_id] ?? null);

            $lineTotal = round((float) $item->price_snapshot * (int) $item->qty, 2);

            return [
                'id' => $item->id,
                'invoice_id' => $invoice->id,
                'task_id' => $item->task_id,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'item_name' => $name,
                'qty' => (int) $item->qty,
                'price_snapshot' => (float) $item->price_snapshot,
                'price_source' => $item->price_source,
                'discount' => 0.0,
                'line_total' => $lineTotal,
                'created_at' => $item->created_at?->toISOString(),
                'updated_at' => $item->updated_at?->toISOString(),
            ];
        })->values()->all();
    }
}
