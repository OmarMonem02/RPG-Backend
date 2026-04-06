<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTaskService
{
    public function execute(Ticket $ticket, TicketTask $task, array $data): TicketTask
    {
        return DB::transaction(function () use ($ticket, $task, $data): TicketTask {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $task = TicketTask::query()
                ->where('ticket_id', $ticket->id)
                ->lockForUpdate()
                ->findOrFail($task->id);

            if ($ticket->status === Ticket::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot update tasks on a completed ticket.',
                ]);
            }

            $task->update([
                'name' => $data['name'],
                'status' => $data['status'] ?? $task->status,
                'approved_by_client' => $data['approved_by_client'] ?? $task->approved_by_client,
            ]);

            return $task->load('items');
        });
    }
}
