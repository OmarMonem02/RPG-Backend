<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnstoredSaleItemsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
{
    use HasOrderedExportColumns;
    use StylesProfessionalSheets;

    public function __construct(
        private readonly Builder $query,
        ?array $columnKeys = null,
    ) {
        $this->columnKeys = $columnKeys;
    }

    public function title(): string
    {
        return 'Unstored Sale Items';
    }

    public function query(): Builder
    {
        return $this->query;
    }

    protected function exportColumnMap(): array
    {
        return [
            'sale_id' => 'Sale ID',
            'created_at' => 'Sale created at',
            'customer_name' => 'Customer name',
            'customer_phone' => 'Customer phone',
            'seller' => 'Seller',
            'payment_method' => 'Payment method',
            'sale_total' => 'Sale total',
            'item_name' => 'Unstored item name',
            'description' => 'Description',
            'item_type' => 'Type',
            'qty' => 'Qty',
            'cost_price' => 'Cost (EGP)',
            'sale_price' => 'Sale price (EGP)',
        ];
    }

    protected function mapColumn(string $key, mixed $row): mixed
    {
        /** @var SaleItem $row */
        $sale = $row->sale;

        return match ($key) {
            'sale_id' => $sale?->id,
            'created_at' => $sale?->created_at?->format('Y-m-d H:i:s') ?? '',
            'customer_name' => $sale?->customer?->name ?? '',
            'customer_phone' => $sale?->customer?->phone ?? '',
            'seller' => $sale?->seller?->name ?? '',
            'payment_method' => $sale?->paymentMethod?->name ?? '',
            'sale_total' => (float) ($sale?->total ?? 0),
            'item_name' => $row->custom_name ?? '',
            'description' => $row->custom_description ?? '',
            'item_type' => $row->unstored_type ?? '',
            'qty' => (int) $row->qty,
            'cost_price' => (float) ($row->cost_price ?? 0),
            'sale_price' => (float) $row->selling_price,
            default => null,
        };
    }
}
