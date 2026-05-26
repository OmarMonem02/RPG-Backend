<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\TicketMessage;
use App\Services\TicketTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTicketMessageController extends Controller
{
    public function __construct(
        private readonly TicketTrackingService $trackingService,
    ) {
    }

    public function index(string $token, Request $request): JsonResponse
    {
        $ticket = $this->resolveTicket($token, $request);

        if (! $ticket) {
            return $this->unauthorizedResponse();
        }

        $messages = $ticket->messages()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => TicketMessageResource::collection($messages),
        ]);
    }

    public function store(string $token, StoreTicketMessageRequest $request): JsonResponse
    {
        $ticket = $this->resolveTicket($token, $request);

        if (! $ticket) {
            return $this->unauthorizedResponse();
        }

        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'This ticket is closed. You can view messages but cannot send new ones.',
            ], 422);
        }

        $message = $ticket->messages()->create([
            'sender_type' => TicketMessage::SENDER_CUSTOMER,
            'user_id' => null,
            ...$request->messagePayload(),
        ]);

        return response()->json(new TicketMessageResource($message), 201);
    }

    private function resolveTicket(string $token, Request $request): ?\App\Models\Ticket
    {
        $sessionId = $request->header(TicketTrackingService::SESSION_HEADER);

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return $this->trackingService->resolveSession($sessionId, $token);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json(['message' => 'Verification required.'], 401);
    }
}
