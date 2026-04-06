<?php

namespace App\Services\Tickets;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTaskService
{
    public function execute(Ticket $ticket, TicketTask $task): void
    {
        DB::transaction(function () use ($ticket, $task): void {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $task = TicketTask::query()
                ->with('items')
                ->where('ticket_id', $ticket->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            if ($ticket->status === Ticket::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot delete tasks from a completed ticket.',
                ]);
            }

            foreach ($task->items as $item) {
                if ($item->item_type !== TicketItem::ITEM_TYPE_PRODUCT) {
                    continue;
                }

                $product = Product::query()->lockForUpdate()->find($item->item_id);

                if ($product !== null) {
                    $product->increment('qty', $item->qty);
                }
            }

            $task->delete();
        });
    }
}
