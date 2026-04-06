<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Services\Invoices\GenerateInvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteTicketService
{
    public function __construct(
        private readonly GenerateInvoiceService $generateInvoiceService,
    ) {
    }

    public function execute(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket): Ticket {
            $ticket = Ticket::query()
                ->with('tasks')
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            if ($ticket->status !== Ticket::STATUS_IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'ticket' => 'Only in-progress tickets can be completed.',
                ]);
            }

            if ($ticket->tasks->isEmpty()) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot complete a ticket without tasks.',
                ]);
            }

            if ($ticket->tasks->contains(fn ($task) => $task->status !== 'completed')) {
                throw ValidationException::withMessages([
                    'tasks' => 'All tasks must be completed before completing the ticket.',
                ]);
            }

            $ticket->update(['status' => Ticket::STATUS_COMPLETED]);
            $this->generateInvoiceService->forTicket($ticket);

            return $ticket->load(['customer', 'customerBike', 'tasks.items', 'items']);
        });
    }
}
