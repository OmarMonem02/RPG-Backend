<?php

namespace App\Exports;

use App\Exports\Concerns\HasOrderedExportColumns;
use App\Exports\Concerns\StylesProfessionalSheets;
use App\Models\TicketItem;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;

class UnstoredTicketItemsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle, WithEvents
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
        return 'Unstored Ticket Items';
    }

    public function query(): Builder
    {
        return $this->query;
    }

    protected function exportColumnMap(): array
    {
        return [
            'ticket_id' => 'Ticket ID',
            'created_at' => 'Ticket opened at',
            'status' => 'Ticket status',
            'customer_name' => 'Customer name',
            'customer_phone' => 'Customer phone',
            'task_name' => 'Task name',
            'ticket_total' => 'Ticket total',
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
        /** @var TicketItem $row */
        $ticket = $row->ticket;
        $task = $row->task;

        return match ($key) {
            'ticket_id' => $ticket?->id,
            'created_at' => $ticket?->created_at?->format('Y-m-d H:i:s') ?? '',
            'status' => $ticket?->status ?? '',
            'customer_name' => $ticket?->customer?->name ?? '',
            'customer_phone' => $ticket?->customer?->phone ?? '',
            'task_name' => $task?->name ?? '',
            'ticket_total' => (float) ($ticket?->total ?? 0),
            'item_name' => $row->custom_name ?? '',
            'description' => $row->custom_description ?? '',
            'item_type' => $row->Unstored_type ?? '',
            'qty' => (int) $row->qty,
            'cost_price' => (float) ($row->cost_price ?? 0),
            'sale_price' => (float) $row->price_snapshot,
            default => null,
        };
    }
}
