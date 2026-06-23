<?php

namespace App\Services;

use App\Models\MaintenancePart;
use App\Models\MaintenanceService;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\User;
use App\Support\ItemDiscountResolver;
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
                        if (! empty($itemData['spare_part_id'])) {
                            $price = $this->convertCatalogPriceToEgp(
                                SparePart::find($itemData['spare_part_id']),
                            );
                        } elseif (! empty($itemData['maintenance_part_id'])) {
                            $price = $this->convertCatalogPriceToEgp(
                                MaintenancePart::find($itemData['maintenance_part_id']),
                            );
                        } elseif (! empty($itemData['product_id'])) {
                            $price = $this->convertCatalogPriceToEgp(
                                Product::find($itemData['product_id']),
                            );
                        } elseif (! empty($itemData['maintenance_service_id'])) {
                            $price = $this->convertCatalogPriceToEgp(
                                MaintenanceService::find($itemData['maintenance_service_id']),
                            );
                        }
                    }

                    $discount = $this->resolveItemDiscount(
                        $userId,
                        (float) ($itemData['discount'] ?? 0),
                        (float) $price,
                        ! empty($itemData['spare_part_id']) ? (int) $itemData['spare_part_id'] : null,
                        ! empty($itemData['maintenance_service_id']) ? (int) $itemData['maintenance_service_id'] : null,
                        ! empty($itemData['product_id']) ? (int) $itemData['product_id'] : null,
                    );
                    $lineSubtotal = ((float) $price - $discount) * (int) $itemData['qty'];
                    $taskSubtotal += $lineSubtotal;

                    $ticket->items()->create([
                        'task_id' => $task->id,
                        'spare_part_id' => $itemData['spare_part_id'] ?? null,
                        'maintenance_part_id' => $itemData['maintenance_part_id'] ?? null,
                        'maintenance_service_id' => $itemData['maintenance_service_id'] ?? null,
                        'product_id' => $itemData['product_id'] ?? null,
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
        return $task->load(['items.sparePart', 'items.maintenanceService', 'items.product']);
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
            $isUnstored = filter_var($data['is_unstored'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $price = (float) ($data['price_snapshot'] ?? 0);

            if ($isUnstored) {
                $price = (float) ($data['price_snapshot'] ?? 0);
            } elseif ($price <= 0) {
                if (! empty($data['spare_part_id'])) {
                    $price = $this->convertCatalogPriceToEgp(
                        SparePart::find($data['spare_part_id']),
                    );
                } elseif (! empty($data['maintenance_part_id'])) {
                    $price = $this->convertCatalogPriceToEgp(
                        MaintenancePart::find($data['maintenance_part_id']),
                    );
                } elseif (! empty($data['product_id'])) {
                    $price = $this->convertCatalogPriceToEgp(
                        Product::find($data['product_id']),
                    );
                } elseif (! empty($data['maintenance_service_id'])) {
                    $price = $this->convertCatalogPriceToEgp(
                        MaintenanceService::find($data['maintenance_service_id']),
                    );
                }
            }

            $discount = $isUnstored
                ? (float) ($data['discount'] ?? 0)
                : ItemDiscountResolver::resolveUnitDiscount(
                $user,
                (float) ($data['discount'] ?? 0),
                (float) $price,
                ! empty($data['product_id']) ? (int) $data['product_id'] : null,
                ! empty($data['spare_part_id']) ? (int) $data['spare_part_id'] : null,
                ! empty($data['maintenance_service_id']) ? (int) $data['maintenance_service_id'] : null,
                null,
                ! empty($data['maintenance_part_id']) ? (int) $data['maintenance_part_id'] : null,
                approvalRequestId: isset($data['discount_approval_request_id'])
                    ? (int) $data['discount_approval_request_id']
                    : null,
                consumedTicketId: (int) $task->ticket_id,
            );
            $lineSubtotal = ((float) $price - $discount) * (int) $data['qty'];

            $item = $task->items()->create([
                'ticket_id' => $task->ticket_id,
                'spare_part_id' => $isUnstored ? null : ($data['spare_part_id'] ?? null),
                'maintenance_part_id' => $isUnstored ? null : ($data['maintenance_part_id'] ?? null),
                'maintenance_service_id' => $isUnstored ? null : ($data['maintenance_service_id'] ?? null),
                'product_id' => $isUnstored ? null : ($data['product_id'] ?? null),
                'is_unstored' => $isUnstored,
                'custom_name' => $isUnstored ? ($data['custom_name'] ?? null) : null,
                'custom_description' => $isUnstored ? ($data['custom_description'] ?? null) : null,
                'unstored_type' => $isUnstored ? ($data['unstored_type'] ?? null) : null,
                'cost_price' => $isUnstored ? ($data['cost_price'] ?? null) : null,
                'price_snapshot' => $price,
                'discount' => $discount,
                'qty' => $data['qty'],
                'subtotal' => $lineSubtotal,
            ]);

            $this->updateTaskTotal($task);
            $this->updateTicketTotal($task->ticket);

            return $item->load(['sparePart', 'maintenancePart', 'maintenanceService', 'product']);
        });
    }

    public function updateItem(TicketItem $item, array $data, ?User $user = null): TicketItem
    {
        return DB::transaction(function () use ($item, $data, $user) {
            $approvalRequestId = isset($data['discount_approval_request_id'])
                ? (int) $data['discount_approval_request_id']
                : null;

            if (array_key_exists('discount', $data)) {
                $data['discount'] = ItemDiscountResolver::resolveUnitDiscount(
                    $user,
                    (float) $data['discount'],
                    (float) ($data['price_snapshot'] ?? $item->price_snapshot),
                    $item->product_id ? (int) $item->product_id : null,
                    $item->spare_part_id ? (int) $item->spare_part_id : null,
                    $item->maintenance_service_id ? (int) $item->maintenance_service_id : null,
                    null,
                    $item->maintenance_part_id ? (int) $item->maintenance_part_id : null,
                    approvalRequestId: $approvalRequestId,
                    consumedTicketId: (int) $item->ticket_id,
                );
            }

            unset($data['discount_approval_request_id']);
            $item->update($data);

            // Recalculate subtotal for this item
            $lineSubtotal = ((float) $item->price_snapshot - (float) ($item->discount ?? 0)) * (int) $item->qty;
            $item->update(['subtotal' => $lineSubtotal]);

            $this->updateTaskTotal($item->task);
            $this->updateTicketTotal($item->ticket);

            if ($approvalRequestId && (float) ($item->discount ?? 0) > 0 && $user) {
                ItemDiscountResolver::consumeItemApproval(
                    $approvalRequestId,
                    (int) $user->id,
                    (float) $item->discount,
                    consumedTicketId: (int) $item->ticket_id,
                );
            }

            return $item->load(['sparePart', 'maintenancePart', 'maintenanceService', 'product']);
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
        $ticket->refresh();
        $itemsSubtotal = (float) $ticket->tasks()->sum('subtotal');
        $discount = (float) $ticket->discount;

        $updates = [
            'total' => max(0, $itemsSubtotal - $discount),
        ];

        if ($discount > $itemsSubtotal) {
            $discount = $itemsSubtotal;
            $updates['discount'] = $discount;
            $updates['total'] = 0;
        }

        $ticket->update($updates);
    }

    public function updateDiscount(Ticket $ticket, User $user, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $user, $data) {
            if ($ticket->status === 'closed') {
                throw ValidationException::withMessages([
                    'discount' => ['Cannot update discount on a closed ticket.'],
                ]);
            }

            if (! in_array($ticket->status, ['in_progress', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'discount' => ['Overall discount can only be applied while the ticket is in progress or completed.'],
                ]);
            }

            if ($user->role === User::ROLE_TECHNICIAN) {
                throw ValidationException::withMessages([
                    'discount' => ['Only staff can apply an overall ticket discount.'],
                ]);
            }

            $discount = (float) ($data['discount'] ?? 0);
            $itemsSubtotal = (float) $ticket->tasks()->sum('subtotal');

            if ($discount > $itemsSubtotal) {
                throw ValidationException::withMessages([
                    'discount' => ['Overall ticket discount cannot exceed the items subtotal.'],
                ]);
            }

            $ticket->update(['discount' => $discount]);
            $this->updateTicketTotal($ticket);

            $approvalRequestId = isset($data['discount_approval_request_id'])
                ? (int) $data['discount_approval_request_id']
                : null;

            if ($approvalRequestId && $discount > 0) {
                app(ApprovalRequestService::class)->consumeApprovedRequest(
                    $approvalRequestId,
                    (int) $user->id,
                    $discount,
                    consumedSaleId: null,
                    consumedTicketId: (int) $ticket->id,
                );
            }

            return $ticket->fresh(Ticket::detailRelations());
        });
    }

    public function close(Ticket $ticket, array $data, ?User $user = null): Ticket
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['This ticket is already closed.'],
            ]);
        }

        if (! in_array($ticket->status, ['completed', 'partial'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Finish work on this ticket before closing it.'],
            ]);
        }

        $amountPaid = (float) ($data['amount_paid'] ?? 0);
        $oldAmountPaid = (float) $ticket->amount_paid;
        $total = (float) $ticket->total;

        if ($oldAmountPaid > 0) {
            $isChangingAmount = $amountPaid < $oldAmountPaid;
            $isChangingMethod = isset($data['payment_method']) && $data['payment_method'] !== $ticket->payment_method;

            if ($isChangingAmount || $isChangingMethod) {
                $this->verifyAdminPassword($user, $data['admin_password'] ?? null, 'Administrator password is required to change a recorded payment.');
            }
        }

        $status = 'closed';
        if ($total > 0 && $amountPaid < $total) {
            $status = 'partial';
        }

        $ticket->update([
            'status' => $status,
            'payment_method' => $data['payment_method'] ?? $ticket->payment_method,
            'amount_paid' => $amountPaid,
            'closed_at' => $status === 'closed' ? now() : null,
        ]);

        return $ticket->fresh();
    }

    public function recordPayment(Ticket $ticket, array $data, ?User $user = null): Ticket
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages([
                'status' => ['Cannot record payment on a closed ticket.'],
            ]);
        }

        $amountPaid = (float) ($data['amount_paid'] ?? 0);
        $oldAmountPaid = (float) $ticket->amount_paid;

        if ($oldAmountPaid > 0) {
            $isChangingAmount = $amountPaid < $oldAmountPaid;
            $isChangingMethod = isset($data['payment_method']) && $data['payment_method'] !== $ticket->payment_method;

            if ($isChangingAmount || $isChangingMethod) {
                $this->verifyAdminPassword($user, $data['admin_password'] ?? null, 'Administrator password is required to change a recorded payment.');
            }
        }

        $ticket->update([
            'payment_method' => $data['payment_method'] ?? $ticket->payment_method,
            'amount_paid' => $amountPaid,
        ]);

        return $ticket->fresh();
    }

    private function verifyAdminPassword(?User $user, ?string $password, string $message): void
    {
        if (! $user || $user->role !== User::ROLE_ADMIN) {
            throw ValidationException::withMessages([
                'admin_password' => ['Only administrators can perform this action.'],
            ]);
        }

        if (! $password) {
            throw ValidationException::withMessages([
                'admin_password' => [$message],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'admin_password' => ['Invalid administrator password.'],
            ]);
        }
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
            $this->verifyAdminPassword($user, $password, 'Administrator password is required to reopen this ticket.');
        }

        $ticket->update([
            'status' => 'in_progress',
            'closed_at' => null,
        ]);

        return $ticket->fresh();
    }

    /**
     * @return array{usd: float, eur: float}
     */
    private function exchangeRates(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['exchange_rate', 'exchange_rate_eur'])
            ->pluck('value', 'key');

        return [
            'usd' => (float) ($settings->get('exchange_rate') ?: 1),
            'eur' => (float) ($settings->get('exchange_rate_eur') ?: 1),
        ];
    }

    private function convertAmountToEgp(float $amount, string $currency): float
    {
        $rates = $this->exchangeRates();
        $multiplier = match ($currency) {
            'USD' => $rates['usd'] > 0 ? $rates['usd'] : 1,
            'EUR' => $rates['eur'] > 0 ? $rates['eur'] : 1,
            default => 1,
        };

        return round($amount * $multiplier, 2);
    }

    private function convertCatalogPriceToEgp(SparePart|MaintenancePart|Product|MaintenanceService|null $catalog): float
    {
        if (! $catalog) {
            return 0;
        }

        $price = isset($catalog->service_price)
            ? (float) $catalog->service_price
            : (float) ($catalog->sale_price ?? 0);

        $saleCurrency = (string) ($catalog->sale_currency ?? 'EGP');

        return $this->convertAmountToEgp($price, $saleCurrency);
    }
}
