<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'customer_bike_id' => $this->customer_bike_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'client_note' => $this->client_note,
            'customer' => $this->whenLoaded('customer'),
            'customer_bike' => $this->whenLoaded('customerBike'),
            'tasks' => $this->whenLoaded('tasks'),
            'items' => $this->whenLoaded('items'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
