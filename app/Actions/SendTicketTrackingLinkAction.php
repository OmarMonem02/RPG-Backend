<?php

namespace App\Actions;

use App\Jobs\SendTicketTrackingWhatsAppJob;
use App\Models\Ticket;
use App\Services\TicketTrackingService;
use App\Support\PhoneNormalizer;
use RuntimeException;

class SendTicketTrackingLinkAction
{
    public function __construct(
        private readonly TicketTrackingService $trackingService,
    ) {
    }

    /**
     * @return array{sent_at: string, tracking_url: string, public_token: string}
     */
    public function execute(Ticket $ticket): array
    {
        $ticket->loadMissing('customer');

        $customerPhone = $ticket->customer?->phone;
        if (! filled($customerPhone)) {
            throw new RuntimeException('Customer does not have a phone number on file.');
        }

        $ticket = $this->trackingService->ensurePublicToken($ticket);
        $trackingUrl = $this->trackingService->buildTrackingUrl($ticket);

        SendTicketTrackingWhatsAppJob::dispatch(
            $ticket,
            PhoneNormalizer::forWhatsApp($customerPhone),
            $trackingUrl
        );

        $this->trackingService->recordLinkSent($ticket->fresh());

        $freshTicket = $ticket->fresh();

        return [
            'sent_at' => $freshTicket->tracking_link_sent_at?->toIso8601String() ?? now()->toIso8601String(),
            'tracking_url' => $trackingUrl,
            'public_token' => (string) $freshTicket->public_token,
        ];
    }
}
