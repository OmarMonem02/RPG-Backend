<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\Sale;
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

class SalesListExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Sales';
    }

    public function query(): Builder
    {
        return $this->query;
    }

    protected function exportColumnMap(): array
    {
        return [
            'sale_id' => 'Sale ID',
            'created_at' => 'Created at',
            'customer_name' => 'Customer name',
            'customer_phone' => 'Customer phone',
            'channel' => 'Channel',
            'delivery_status' => 'Delivery status',
            'payment_method' => 'Payment method',
            'seller' => 'Seller',
            'cashier' => 'Cashier',
            'discount' => 'Discount',
            'shipping_fee' => 'Shipping fee',
            'total' => 'Total',
            'maintenance_sale' => 'Maintenance sale',
            'line_item_count' => 'Line item count',
            'line_items_summary' => 'Line items summary',
        ];
    }

    protected function mapColumn(string $key, mixed $sale): mixed
    {
        /** @var Sale $sale */
        return match ($key) {
            'sale_id' => $sale->id,
            'created_at' => $sale->created_at?->format('Y-m-d H:i:s') ?? '',
            'customer_name' => $sale->customer?->name ?? '',
            'customer_phone' => $sale->customer?->phone ?? '',
            'channel' => $sale->type,
            'delivery_status' => $sale->delivery_status ?? '',
            'payment_method' => $sale->paymentMethod?->name ?? '',
            'seller' => $sale->seller?->name ?? '',
            'cashier' => $sale->user?->name ?? '',
            'discount' => (float) $sale->discount,
            'shipping_fee' => (float) $sale->shipping_fee,
            'total' => (float) $sale->total,
            'maintenance_sale' => $sale->is_maintenance ? 'Yes' : 'No',
            'line_item_count' => $sale->items->count(),
            'line_items_summary' => $sale->items
                ->map(function (SaleItem $item): string {
                    $label = $this->inventory->describeSaleItem($item);

                    return (string) $item->qty . ' × ' . $label;
                })
                ->implode('; '),
            default => null,
        };
    }
}
