<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddTicketNoteRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use App\Services\Tickets\AddTicketNoteService;
use App\Services\Tickets\CompleteTicketService;
use App\Services\Tickets\CreateTicketService;
use App\Services\Tickets\ReopenTicketService;
use App\Services\Tickets\StartTicketService;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    public function __construct(
        private readonly CreateTicketService $createTicketService,
        private readonly StartTicketService $startTicketService,
        private readonly CompleteTicketService $completeTicketService,
        private readonly ReopenTicketService $reopenTicketService,
        private readonly AddTicketNoteService $addTicketNoteService,
    ) {
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->createTicketService->execute($request->validated());

        return response()->json([
            'message' => 'Ticket created successfully.',
            'data' => $ticket,
        ], 201);
    }

    public function start(Ticket $ticket): JsonResponse
    {
        $ticket = $this->startTicketService->execute($ticket);

        return response()->json([
            'message' => 'Ticket started successfully.',
            'data' => $ticket,
        ]);
    }

    public function complete(Ticket $ticket): JsonResponse
    {
        $ticket = $this->completeTicketService->execute($ticket);

        return response()->json([
            'message' => 'Ticket completed successfully.',
            'data' => $ticket,
        ]);
    }

    public function reopen(Ticket $ticket): JsonResponse
    {
        $ticket = $this->reopenTicketService->execute($ticket);

        return response()->json([
            'message' => 'Ticket reopened successfully.',
            'data' => $ticket,
        ]);
    }

    public function addNote(AddTicketNoteRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->addTicketNoteService->execute($ticket, $request->validated());

        return response()->json([
            'message' => 'Ticket note added successfully.',
            'data' => $ticket,
        ]);
    }
}
