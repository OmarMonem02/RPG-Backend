<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class AddTicketNoteService
{
    public function execute(Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $data): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $column = $data['type'] === 'client' ? 'client_note' : 'notes';
            $existing = trim((string) ($ticket->{$column} ?? ''));
            $incoming = trim($data['note']);

            $ticket->update([
                $column => $existing === '' ? $incoming : $existing.PHP_EOL.$incoming,
            ]);

            return $ticket->load(['customer', 'customerBike', 'tasks.items', 'items']);
        });
    }
}
