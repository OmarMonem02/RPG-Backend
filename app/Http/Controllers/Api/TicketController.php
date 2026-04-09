<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketRequest;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $ticketService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(Ticket::with(['tasks', 'items', 'customer', 'customerBike', 'user'])->paginate(20));
    }

    public function store(TicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated(), (int) $request->user()->id);

        return response()->json($ticket, 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket->load(['tasks', 'items', 'customer', 'customerBike', 'user']));
    }

    public function updateStatus(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
        ]);
        $ticket->update(['status' => $data['status']]);

        return response()->json($ticket);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json([], 204);
    }
}
