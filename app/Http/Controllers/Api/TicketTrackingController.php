<?php

namespace App\Http\Controllers\Api;

use App\Actions\SendTicketTrackingLinkAction;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketTrackingService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TicketTrackingController extends Controller
{
    public function __construct(
        private readonly TicketTrackingService $trackingService,
        private readonly SendTicketTrackingLinkAction $sendTrackingLink,
    ) {
    }

    public function sendTrackingLink(Ticket $ticket): JsonResponse
    {
        try {
            $result = $this->sendTrackingLink->execute($ticket);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function regenerateTrackingToken(Ticket $ticket): JsonResponse
    {
        $ticket = $this->trackingService->regeneratePublicToken($ticket);

        return response()->json([
            'public_token' => $ticket->public_token,
            'tracking_url' => $this->trackingService->buildTrackingUrl($ticket),
            'message' => 'Tracking link regenerated. Previous links are no longer valid.',
        ]);
    }
}
