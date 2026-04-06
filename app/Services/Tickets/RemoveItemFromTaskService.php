<?php

namespace App\Services\Tickets;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\StockLog;
use App\Services\Inventory\AdjustStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveItemFromTaskService
{
    public function __construct(
        private readonly AdjustStockService $adjustStockService,
    ) {
    }

    public function execute(Ticket $ticket, TicketTask $task, TicketItem $item): void
    {
        DB::transaction(function () use ($ticket, $task, $item): void {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $task = TicketTask::query()
                ->where('ticket_id', $ticket->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            $item = TicketItem::query()
                ->where('ticket_id', $ticket->id)
                ->where('task_id', $task->id)
                ->lockForUpdate()
                ->findOrFail($item->id);

            if ($ticket->status === Ticket::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot remove items from a completed ticket.',
                ]);
            }

            if ($item->item_type === TicketItem::ITEM_TYPE_PRODUCT) {
                $product = Product::query()->find($item->item_id);

                if ($product !== null) {
                    $this->adjustStockService->execute(
                        $product,
                        $item->qty,
                        StockLog::CHANGE_TYPE_RETURN,
                        'ticket',
                        $ticket->id
                    );
                }
            }

            $item->delete();
        });
    }
}
