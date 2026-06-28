<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\SaleItem;
use App\Services\SaleInventoryService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class SaleSoldItemsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    /**
     * @param  list<string>|null  $columnKeys
     */
    public function __construct(
        private readonly Builder $query,
        private readonly SaleInventoryService $inventory,
        ?array $columnKeys = null,
    ) {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Sold Items';
    }

    public function query(): Builder
    {
        return $this->query;
    }

    protected function exportColumnMap(): array
    {
        return [
            'sale_id' => 'Sale ID',
            'sale_created_at' => 'Sale created at',
            'customer_name' => 'Customer name',
            'customer_phone' => 'Customer phone',
            'channel' => 'Channel',
            'seller' => 'Seller',
            'payment_method' => 'Payment method',
            'line_item_id' => 'Line item ID',
            'item_type' => 'Item type',
            'item_name' => 'Item name',
            'sku' => 'SKU',
            'qty' => 'Qty',
            'returned_qty' => 'Returned qty',
            'remaining_qty' => 'Remaining qty',
            'unit_price' => 'Unit price',
            'line_discount' => 'Line discount',
            'line_total' => 'Line total',
            'item_status' => 'Item status',
            'is_unstored' => 'Unstored item',
        ];
    }

    protected function mapColumn(string $key, mixed $row): mixed
    {
        /** @var SaleItem $row */
        $sale = $row->sale;
        $remainingQty = $row->remainingQty();
        $lineTotal = max(0, ((float) $row->selling_price - (float) $row->discount) * $remainingQty);

        return match ($key) {
            'sale_id' => $sale?->id,
            'sale_created_at' => $sale?->created_at?->format('Y-m-d H:i:s') ?? '',
            'customer_name' => $sale?->customer?->name ?? '',
            'customer_phone' => $sale?->customer?->phone ?? '',
            'channel' => $sale?->type ?? '',
            'seller' => $sale?->seller?->name ?? '',
            'payment_method' => $sale?->paymentMethod?->name ?? '',
            'line_item_id' => $row->id,
            'item_type' => $row->getSellableType() ?? ($row->unstored_type ?? ''),
            'item_name' => $this->inventory->describeSaleItem($row),
            'sku' => $this->resolveSku($row),
            'qty' => (int) $row->qty,
            'returned_qty' => (int) $row->returned_qty,
            'remaining_qty' => $remainingQty,
            'unit_price' => (float) $row->selling_price,
            'line_discount' => (float) $row->discount,
            'line_total' => $lineTotal,
            'item_status' => $row->status ?? '',
            'is_unstored' => $row->is_unstored ? 'Yes' : 'No',
            default => null,
        };
    }

    private function resolveSku(SaleItem $item): string
    {
        if ($item->isUnstored()) {
            return '';
        }

        $item->loadMissing('product', 'sparePart', 'maintenancePart', 'bikeForSale');

        return match (true) {
            ! is_null($item->product) => (string) ($item->product->sku ?? ''),
            ! is_null($item->sparePart) => (string) ($item->sparePart->sku ?? ''),
            ! is_null($item->maintenancePart) => (string) ($item->maintenancePart->sku ?? ''),
            ! is_null($item->bikeForSale) => (string) ($item->bikeForSale->vin ?? ''),
            default => '',
        };
    }
}
