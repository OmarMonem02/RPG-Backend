<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\WhatsAppCloudClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTicketTrackingWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $toPhone,
        public string $trackingUrl,
    ) {
    }

    public function handle(WhatsAppCloudClient $client): void
    {
        $this->ticket->loadMissing('customer');

        $customerName = $this->ticket->customer?->name ?? 'Customer';
        $ticketNumber = str_pad((string) $this->ticket->id, 6, '0', STR_PAD_LEFT);

        $client->sendTemplateMessage(
            $this->toPhone,
            (string) config('services.whatsapp.tracking_template_name'),
            (string) config('services.whatsapp.template_language'),
            [
                [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'parameter_name' => 'customer_name',
                            'text' => $customerName,
                        ],
                    ],
                ],
                [
                    'type' => 'body',
                    'parameters' => [
                        [
                            'type' => 'text',
                            'parameter_name' => 'ticket_number',
                            'text' => $ticketNumber,
                        ],
                        [
                            'type' => 'text',
                            'parameter_name' => 'tracking_url',
                            'text' => $this->trackingUrl,
                        ],
                    ],
                ],
            ]
        );
    }
}
