<?php

namespace App\Exports;

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
    use StylesProfessionalSheets;

    public function __construct(
        private readonly Builder $query,
        private readonly SaleInventoryService $inventory,
    ) {
    }

    public function title(): string
    {
        return 'Sales';
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Sale ID',
            'Created at',
            'Customer name',
            'Customer phone',
            'Channel',
            'Status',
            'Delivery status',
            'Payment method',
            'Seller',
            'Cashier',
            'Discount',
            'Shipping fee',
            'Total',
            'Maintenance sale',
            'Line item count',
            'Line items summary',
        ];
    }

    /**
     * @param  Sale  $sale
     */
    public function map($sale): array
    {
        $summary = $sale->items
            ->map(function (SaleItem $item): string {
                $label = $this->inventory->describeSaleItem($item);

                return (string) $item->qty . ' × ' . $label;
            })
            ->implode('; ');

        return [
            $sale->id,
            $sale->created_at?->format('Y-m-d H:i:s') ?? '',
            $sale->customer?->name ?? '',
            $sale->customer?->phone ?? '',
            $sale->type,
            $sale->status,
            $sale->delivery_status ?? '',
            $sale->paymentMethod?->name ?? '',
            $sale->seller?->name ?? '',
            $sale->user?->name ?? '',
            (float) $sale->discount,
            (float) $sale->shipping_fee,
            (float) $sale->total,
            $sale->is_maintenance ? 'Yes' : 'No',
            $sale->items->count(),
            $summary,
        ];
    }
}
