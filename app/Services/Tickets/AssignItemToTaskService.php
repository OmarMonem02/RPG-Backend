<?php

namespace App\Services\Tickets;

use App\Models\Product;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignItemToTaskService
{
    public function execute(Ticket $ticket, TicketTask $task, array $data): TicketItem
    {
        return DB::transaction(function () use ($ticket, $task, $data): TicketItem {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $task = TicketTask::query()
                ->where('ticket_id', $ticket->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            if ($ticket->status === Ticket::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot assign items to a completed ticket.',
                ]);
            }

            return match ($data['item_type']) {
                TicketItem::ITEM_TYPE_PRODUCT => $this->assignProduct($ticket, $task, $data),
                TicketItem::ITEM_TYPE_SERVICE => $this->assignService($ticket, $task, $data),
                default => throw ValidationException::withMessages([
                    'item_type' => 'Unsupported ticket item type.',
                ]),
            };
        });
    }

    private function assignProduct(Ticket $ticket, TicketTask $task, array $data): TicketItem
    {
        $product = Product::query()->lockForUpdate()->findOrFail($data['item_id']);
        $qty = (int) $data['qty'];

        if ($product->qty < $qty) {
            throw ValidationException::withMessages([
                'qty' => 'Requested quantity exceeds available stock.',
            ]);
        }

        [$priceSnapshot, $priceSource] = $this->resolveProductPrice($ticket, $product, $data['price_source'] ?? null);

        $item = TicketItem::query()->create([
            'ticket_id' => $ticket->id,
            'task_id' => $task->id,
            'item_type' => TicketItem::ITEM_TYPE_PRODUCT,
            'item_id' => $product->id,
            'price_snapshot' => $priceSnapshot,
            'price_source' => $priceSource,
            'qty' => $qty,
        ]);

        $product->decrement('qty', $qty);

        return $item->fresh();
    }

    private function assignService(Ticket $ticket, TicketTask $task, array $data): TicketItem
    {
        if (($data['price_source'] ?? TicketItem::PRICE_SOURCE_CURRENT) !== TicketItem::PRICE_SOURCE_CURRENT) {
            throw ValidationException::withMessages([
                'price_source' => 'Services can only use the current price.',
            ]);
        }

        $service = Service::query()->findOrFail($data['item_id']);

        return TicketItem::query()->create([
            'ticket_id' => $ticket->id,
            'task_id' => $task->id,
            'item_type' => TicketItem::ITEM_TYPE_SERVICE,
            'item_id' => $service->id,
            'price_snapshot' => $service->price,
            'price_source' => TicketItem::PRICE_SOURCE_CURRENT,
            'qty' => (int) $data['qty'],
        ]);
    }

    private function resolveProductPrice(Ticket $ticket, Product $product, ?string $requestedSource): array
    {
        $previousItem = TicketItem::query()
            ->where('ticket_id', $ticket->id)
            ->where('item_type', TicketItem::ITEM_TYPE_PRODUCT)
            ->where('item_id', $product->id)
            ->latest('id')
            ->first();

        if ($requestedSource === TicketItem::PRICE_SOURCE_OLD) {
            if ($previousItem === null) {
                throw ValidationException::withMessages([
                    'price_source' => 'Old price is unavailable because this product has not been used in the ticket before.',
                ]);
            }

            return [(float) $previousItem->price_snapshot, TicketItem::PRICE_SOURCE_OLD];
        }

        return [(float) $product->selling_price, TicketItem::PRICE_SOURCE_CURRENT];
    }
}
