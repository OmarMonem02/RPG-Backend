<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Resources\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\JsonResponse;

class TicketMessageController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $messages = $ticket->messages()
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => TicketMessageResource::collection($messages),
        ]);
    }

    public function store(Ticket $ticket, StoreTicketMessageRequest $request): JsonResponse
    {
        $message = $ticket->messages()->create([
            'sender_type' => TicketMessage::SENDER_STAFF,
            'user_id' => (int) $request->user()->id,
            ...$request->messagePayload(),
        ]);

        $message->load('user');

        return response()->json(new TicketMessageResource($message), 201);
    }
}
