<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddTicketNoteRequest;
use App\Http\Requests\ListTicketsRequest;
use App\Http\Resources\TicketResource;
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
    ) {}

    public function index(ListTicketsRequest $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->with(['customer', 'customerBike'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->validated()['status']))
            ->when($request->filled('customer_id'), fn ($query) => $query->where('customer_id', $request->validated()['customer_id']))
            ->when($request->filled('customer_bike_id'), fn ($query) => $query->where('customer_bike_id', $request->validated()['customer_bike_id']))
            ->latest('id')
            ->paginate($request->validated()['per_page'] ?? 15);

        return $this->successResponse('Tickets retrieved successfully.', [
            'items' => TicketResource::collection($tickets->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return $this->successResponse(
            'Ticket retrieved successfully.',
            new TicketResource($ticket->load(['customer', 'customerBike', 'tasks', 'items']))
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->createTicketService->execute($request->validated());

        return $this->successResponse('Ticket created successfully.', new TicketResource($ticket), 201);
    }

    public function start(Ticket $ticket): JsonResponse
    {
        $ticket = $this->startTicketService->execute($ticket);

        return $this->successResponse('Ticket started successfully.', new TicketResource($ticket));
    }

    public function complete(Ticket $ticket): JsonResponse
    {
        $ticket = $this->completeTicketService->execute($ticket);

        return $this->successResponse('Ticket completed successfully.', new TicketResource($ticket));
    }

    public function reopen(Ticket $ticket): JsonResponse
    {
        $ticket = $this->reopenTicketService->execute($ticket);

        return $this->successResponse('Ticket reopened successfully.', new TicketResource($ticket));
    }

    public function addNote(AddTicketNoteRequest $request, Ticket $ticket): JsonResponse
    {
        $ticket = $this->addTicketNoteService->execute($ticket, $request->validated());

        return $this->successResponse('Ticket note added successfully.', new TicketResource($ticket));
    }
}
