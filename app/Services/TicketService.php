<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\SparePart;
use App\Models\MaintenanceService;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function create(array $data, int $userId): Ticket
    {
        return DB::transaction(function () use ($data, $userId) {
            $ticket = Ticket::create([
                'user_id' => $userId,
                'customer_id' => $data['customer_id'],
                'customer_bike_id' => $data['customer_bike_id'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'total' => 0,
            ]);

            $ticketTotal = 0;
            $tasks = $data['tasks'] ?? [];

            foreach ($tasks as $taskData) {
                $task = $ticket->tasks()->create([
                    'name' => $taskData['name'],
                    'status' => $taskData['status'],
                    'subtotal' => 0,
                ]);

                $taskSubtotal = 0;
                $items = $taskData['items'] ?? [];
                foreach ($items as $itemData) {
                    $price = $itemData['price_snapshot'] ?? 0;

                    if ($price <= 0) {
                        if (!empty($itemData['spare_part_id'])) {
                            $sparePart = SparePart::find($itemData['spare_part_id']);
                            $price = $sparePart ? $sparePart->sale_price : 0;
                        } elseif (!empty($itemData['maintenance_service_id'])) {
                            $service = MaintenanceService::find($itemData['maintenance_service_id']);
                            $price = $service ? $service->service_price : 0;
                        }
                    }

                    $lineSubtotal = ((float) $price - (float) ($itemData['discount'] ?? 0)) * (int) $itemData['qty'];
                    $taskSubtotal += $lineSubtotal;

                    $ticket->items()->create([
                        'task_id' => $task->id,
                        'spare_part_id' => $itemData['spare_part_id'] ?? null,
                        'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
                        'price_snapshot' => $price,
                        'discount' => $itemData['discount'] ?? 0,
                        'qty' => $itemData['qty'],
                        'subtotal' => $lineSubtotal,
                    ]);
                }

                $task->update(['subtotal' => $taskSubtotal]);
                $ticketTotal += $taskSubtotal;
            }

            $ticket->update(['total' => $ticketTotal]);

            return $ticket->load(['tasks.items', 'items', 'customer', 'customerBike', 'user']);
        });
    }
    public function addTask(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data) {
            $ticket->tasks()->create([
                'name' => $data['name'],
                'status' => $data['status'] ?? 'pending',
                'subtotal' => 0,
            ]);

            return $ticket->load(['tasks.items', 'items', 'customer', 'customerBike', 'user']);
        });
    }

    public function updateTask(TicketTask $task, array $data): TicketTask
    {
        $task->update($data);
        $this->updateTicketTotal($task->ticket);
        return $task->load('items');
    }

    public function deleteTask(TicketTask $task): void
    {
        DB::transaction(function () use ($task) {
            $ticket = $task->ticket;
            $task->items()->delete();
            $task->delete();
            $this->updateTicketTotal($ticket);
        });
    }

    public function addItem(TicketTask $task, array $data): TicketItem
    {
        return DB::transaction(function () use ($task, $data) {
            $price = $data['price_snapshot'] ?? 0;

            if ($price <= 0) {
                if (!empty($data['spare_part_id'])) {
                    $sparePart = SparePart::find($data['spare_part_id']);
                    $price = $sparePart ? $sparePart->sale_price : 0;
                } elseif (!empty($data['maintenance_service_id'])) {
                    $service = MaintenanceService::find($data['maintenance_service_id']);
                    $price = $service ? $service->service_price : 0;
                }
            }

            $lineSubtotal = ((float) $price - (float) ($data['discount'] ?? 0)) * (int) $data['qty'];

            $item = $task->items()->create([
                'ticket_id' => $task->ticket_id,
                'spare_part_id' => $data['spare_part_id'] ?? null,
                'maintenance_service_id' => $data['maintenance_service_id'] ?? null,
                'price_snapshot' => $price,
                'discount' => $data['discount'] ?? 0,
                'qty' => $data['qty'],
                'subtotal' => $lineSubtotal,
            ]);

            $this->updateTaskTotal($task);
            $this->updateTicketTotal($task->ticket);

            return $item;
        });
    }

    public function updateItem(TicketItem $item, array $data): TicketItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item->update($data);

            // Recalculate subtotal for this item
            $lineSubtotal = ((float) $item->price_snapshot - (float) ($item->discount ?? 0)) * (int) $item->qty;
            $item->update(['subtotal' => $lineSubtotal]);

            $this->updateTaskTotal($item->task);
            $this->updateTicketTotal($item->ticket);

            return $item;
        });
    }

    public function deleteItem(TicketItem $item): void
    {
        DB::transaction(function () use ($item) {
            $task = $item->task;
            $ticket = $item->ticket;
            $item->delete();

            $this->updateTaskTotal($task);
            $this->updateTicketTotal($ticket);
        });
    }

    private function updateTaskTotal(TicketTask $task): void
    {
        $task->update([
            'subtotal' => $task->items()->sum('subtotal')
        ]);
    }

    private function updateTicketTotal(Ticket $ticket): void
    {
        $ticket->update([
            'total' => $ticket->tasks()->sum('subtotal')
        ]);
    }
}
