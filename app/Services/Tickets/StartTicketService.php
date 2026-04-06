<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StartTicketService
{
    public function execute(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status !== Ticket::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'ticket' => 'Only pending tickets can be started.',
                ]);
            }

            $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);

            return $ticket->load(['customer', 'customerBike', 'tasks.items', 'items']);
        });
    }
}
