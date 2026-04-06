<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddTaskService
{
    public function execute(Ticket $ticket, array $data): TicketTask
    {
        return DB::transaction(function () use ($ticket, $data): TicketTask {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($ticket->status === Ticket::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot add tasks to a completed ticket.',
                ]);
            }

            return $ticket->tasks()->create([
                'name' => $data['name'],
                'status' => $data['status'] ?? TicketTask::STATUS_PENDING,
                'approved_by_client' => $data['approved_by_client'] ?? false,
            ])->load('items');
        });
    }
}
