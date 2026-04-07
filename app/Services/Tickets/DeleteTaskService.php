<?php

namespace App\Services\Tickets;

use App\Models\Product;
use App\Models\StockLog;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Services\Inventory\AdjustStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteTaskService
{
    public function __construct(
        private readonly AdjustStockService $adjustStockService,
    ) {}

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

            $task->delete();
        });
    }
}
