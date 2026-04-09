<?php

namespace App\Services;

use App\Models\Ticket;
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

            foreach ($data['tasks'] as $taskData) {
                $task = $ticket->tasks()->create([
                    'name' => $taskData['name'],
                    'status' => $taskData['status'],
                    'subtotal' => 0,
                ]);

                $taskSubtotal = 0;
                foreach ($taskData['items'] as $itemData) {
                    $lineSubtotal = ((float) $itemData['price_snapshot'] - (float) ($itemData['discount'] ?? 0)) * (int) $itemData['qty'];
                    $taskSubtotal += $lineSubtotal;

                    $ticket->items()->create([
                        'task_id' => $task->id,
                        'spare_part_id' => $itemData['spare_part_id'] ?? null,
                        'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
                        'price_snapshot' => $itemData['price_snapshot'],
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
}
