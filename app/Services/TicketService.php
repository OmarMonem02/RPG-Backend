<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\User;
use App\Models\SparePart;
use App\Models\MaintenanceService;
use App\Support\MaxDiscount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

                    $discount = $this->resolveItemDiscount(
                        $userId,
                        (float) ($itemData['discount'] ?? 0),
                        (float) $price,
                        ! empty($itemData['spare_part_id']) ? (int) $itemData['spare_part_id'] : null,
                        ! empty($itemData['maintenance_service_id']) ? (int) $itemData['maintenance_service_id'] : null,
                    );
                    $lineSubtotal = ((float) $price - $discount) * (int) $itemData['qty'];
                    $taskSubtotal += $lineSubtotal;

                    $ticket->items()->create([
                        'task_id' => $task->id,
                        'spare_part_id' => $itemData['spare_part_id'] ?? null,
                        'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
                        'price_snapshot' => $price,
                        'discount' => $discount,
                        'qty' => $itemData['qty'],
                        'subtotal' => $lineSubtotal,
                    ]);
                }

                $task->update(['subtotal' => $taskSubtotal]);
                $ticketTotal += $taskSubtotal;
            }

            $ticket->update(['total' => $ticketTotal]);

            return $ticket->load(Ticket::detailRelations());
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

            return $ticket->load(Ticket::detailRelations());
        });
    }

    public function updateTask(TicketTask $task, array $data): TicketTask
    {
        $task->update($data);
        $this->updateTicketTotal($task->ticket);
        return $task->load(['items.sparePart', 'items.maintenanceService']);
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

    public function addItem(TicketTask $task, array $data, ?User $user = null): TicketItem
    {
        return DB::transaction(function () use ($task, $data, $user) {
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

            $discount = $this->resolveItemDiscount(
                $user?->id,
                (float) ($data['discount'] ?? 0),
                (float) $price,
                ! empty($data['spare_part_id']) ? (int) $data['spare_part_id'] : null,
                ! empty($data['maintenance_service_id']) ? (int) $data['maintenance_service_id'] : null,
            );
            $lineSubtotal = ((float) $price - $discount) * (int) $data['qty'];

            $item = $task->items()->create([
                'ticket_id' => $task->ticket_id,
                'spare_part_id' => $data['spare_part_id'] ?? null,
                'maintenance_service_id' => $data['maintenance_service_id'] ?? null,
                'price_snapshot' => $price,
                'discount' => $discount,
                'qty' => $data['qty'],
                'subtotal' => $lineSubtotal,
            ]);

            $this->updateTaskTotal($task);
            $this->updateTicketTotal($task->ticket);

            return $item->load(['sparePart', 'maintenanceService']);
        });
    }

    public function updateItem(TicketItem $item, array $data, ?User $user = null): TicketItem
    {
        return DB::transaction(function () use ($item, $data, $user) {
            if (array_key_exists('discount', $data)) {
                $data['discount'] = $this->resolveItemDiscount(
                    $user?->id,
                    (float) $data['discount'],
                    (float) ($data['price_snapshot'] ?? $item->price_snapshot),
                    $item->spare_part_id ? (int) $item->spare_part_id : null,
                    $item->maintenance_service_id ? (int) $item->maintenance_service_id : null,
                );
            }

            $item->update($data);

            // Recalculate subtotal for this item
            $lineSubtotal = ((float) $item->price_snapshot - (float) ($item->discount ?? 0)) * (int) $item->qty;
            $item->update(['subtotal' => $lineSubtotal]);

            $this->updateTaskTotal($item->task);
            $this->updateTicketTotal($item->ticket);

            return $item->load(['sparePart', 'maintenanceService']);
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

    public function close(Ticket $ticket, array $data): Ticket
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['This ticket is already closed.'],
            ]);
        }

        if (! in_array($ticket->status, ['completed'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Finish work on this ticket before closing it.'],
            ]);
        }

        $amountPaid = (float) ($data['amount_paid'] ?? 0);
        $total = (float) $ticket->total;

        if ($total > 0 && $amountPaid < $total) {
            throw ValidationException::withMessages([
                'amount_paid' => ['Full payment is required before closing this ticket.'],
            ]);
        }

        $ticket->update([
            'status' => 'closed',
            'payment_method' => $data['payment_method'] ?? null,
            'amount_paid' => $amountPaid,
            'closed_at' => now(),
        ]);

        return $ticket->fresh();
    }

    public function reopen(Ticket $ticket, User $user, ?string $password): Ticket
    {
        if ($ticket->status === 'pending') {
            throw ValidationException::withMessages([
                'status' => ['This ticket has not been started yet.'],
            ]);
        }

        if ($ticket->status === 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['This ticket is already in progress.'],
            ]);
        }

        if ($ticket->isClosedAndFullyPaid()) {
            if ($user->role !== User::ROLE_ADMIN) {
                throw ValidationException::withMessages([
                    'admin_password' => ['Only administrators can reopen a closed and fully paid ticket.'],
                ]);
            }

            if (! $password) {
                throw ValidationException::withMessages([
                    'admin_password' => ['Administrator password is required to reopen this ticket.'],
                ]);
            }

            if (! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'admin_password' => ['Invalid administrator password.'],
                ]);
            }
        }

        $ticket->update([
            'status' => 'in_progress',
            'closed_at' => null,
        ]);

        return $ticket->fresh();
    }

    private function resolveItemDiscount(
        ?int $userId,
        float $requestedDiscount,
        float $unitPrice,
        ?int $sparePartId,
        ?int $maintenanceServiceId,
    ): float {
        $unitPrice = max(0.0, $unitPrice);
        $requestedDiscount = max(0.0, $requestedDiscount);

        if ($requestedDiscount > $unitPrice) {
            throw ValidationException::withMessages([
                'discount' => ['Item discount cannot exceed the unit price.'],
            ]);
        }

        $user = $userId ? User::find($userId) : null;

        if ($user?->role === User::ROLE_TECHNICIAN && $requestedDiscount > 0) {
            throw ValidationException::withMessages([
                'discount' => ['Only staff can apply line discounts on maintenance tickets.'],
            ]);
        }

        if (! $user || $user->role !== User::ROLE_STAFF) {
            return $requestedDiscount;
        }

        [$maxType, $maxValue] = $this->catalogMaxDiscount($sparePartId, $maintenanceServiceId);
        $allowed = MaxDiscount::maxLineDiscount($unitPrice, $maxType, $maxValue);

        if ($requestedDiscount > $allowed + 0.00001) {
            throw ValidationException::withMessages([
                'discount' => [
                    sprintf(
                        'Staff cannot apply more than %s discount on this item.',
                        number_format($allowed, 2, '.', ''),
                    ),
                ],
            ]);
        }

        return $requestedDiscount;
    }

    /**
     * @return array{0: ?string, 1: float}
     */
    private function catalogMaxDiscount(?int $sparePartId, ?int $maintenanceServiceId): array
    {
        if ($sparePartId) {
            $part = SparePart::query()->find($sparePartId);
            if ($part) {
                return [$part->max_discount_type, (float) $part->max_discount_value];
            }
        }

        if ($maintenanceServiceId) {
            $service = MaintenanceService::query()->find($maintenanceServiceId);
            if ($service) {
                return [$service->max_discount_type, (float) $service->max_discount_value];
            }
        }

        return [null, 0.0];
    }
}
