<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use App\Support\TicketTrackingPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketPublicMetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket' => [
                'ticket_number' => str_pad((string) $this->id, 6, '0', STR_PAD_LEFT),
                'status' => $this->status,
                'status_label' => TicketTrackingPresenter::statusLabel($this->status),
            ],
            'shop' => TicketTrackingPresenter::shop(),
            'progress' => [
                'timeline' => TicketTrackingPresenter::buildTimeline($this->status),
            ],
            'requires_phone_verification' => true,
        ];
    }
}
