<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Models\TicketItem;
use App\Support\TicketTrackingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketPublicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tasks = $this->tasks ?? collect();

        return [
            'ticket' => [
                'id' => $this->id,
                'ticket_number' => str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
                'status' => $this->status,
                'status_label' => TicketTrackingPresenter::statusLabel($this->status),
                'total' => (float) $this->total,
                'customer_notes' => $this->customer_notes,
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
                'updated_at_human' => $this->updated_at?->diffForHumans(),
            ],
            'customer' => [
                'name' => $this->customer?->name,
            ],
            'bike' => $this->formatBike(),
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'name' => $task->name,
                'status' => $task->status,
                'status_label' => TicketTrackingPresenter::statusLabel($task->status),
                'subtotal' => (float) $task->subtotal,
                'items' => ($task->items ?? collect())->map(fn (TicketItem $item) => $this->formatItem($item)),
            ])->values(),
            'progress' => TicketTrackingPresenter::buildProgress($this->status, $tasks),
            'shop' => TicketTrackingPresenter::shop(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatBike(): ?array
    {
        $bike = $this->customerBike;
        if (! $bike) {
            return null;
        }

        $blueprint = $bike->bikeBlueprint;

        return [
            'brand' => $blueprint?->brand?->name,
            'model' => $blueprint?->model,
            'year' => $blueprint?->year,
            'vin' => $bike->vin,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatItem(TicketItem $item): array
    {
        $isPart = $item->spare_part_id !== null;

        return [
            'id' => $item->id,
            'type' => $isPart ? 'part' : 'service',
            'label' => $isPart
                ? ($item->sparePart?->name ?? 'Spare part')
                : ($item->maintenanceService?->name ?? 'Service'),
            'qty' => (int) $item->qty,
            'unit_price' => (float) $item->price_snapshot,
            'discount' => (float) $item->discount,
            'subtotal' => (float) $item->subtotal,
        ];
    }
}
