<?php

namespace App\Http\Resources;

use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketMessage */
class TicketMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body ?? '',
            'image_url' => $this->image_url,
            'image_public_id' => $this->image_public_id,
            'sender_type' => $this->sender_type,
            'created_at' => $this->created_at?->toIso8601String(),
            'user' => $this->when(
                $this->sender_type === TicketMessage::SENDER_STAFF && $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ],
            ),
        ];
    }
}
