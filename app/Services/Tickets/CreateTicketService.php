<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

class CreateTicketService
{
    public function execute(array $data): Ticket
    {
        return DB::transaction(function () use ($data): Ticket {
            return Ticket::query()->create([
                'customer_id' => $data['customer_id'],
                'customer_bike_id' => $data['customer_bike_id'],
                'notes' => $data['notes'] ?? null,
                'client_note' => null,
                'status' => Ticket::STATUS_PENDING,
            ])->load(['customer', 'customerBike', 'tasks.items', 'items']);
        });
    }
}
