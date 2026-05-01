<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketItemRequest;
use App\Http\Requests\TicketRequest;
use App\Http\Requests\TicketTaskRequest;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
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
        return response()->json($ticket->load(['tasks.items', 'items', 'customer', 'customerBike', 'user']));
    }

    public function updateStatus(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
        ]);
        $ticket->update(['status' => $data['status']]);

        return response()->json($ticket);
    }

    public function addTask(Ticket $ticket, TicketTaskRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->addTask($ticket, $request->validated());
        return response()->json($ticket);
    }

    public function updateTask(Ticket $ticket, TicketTask $task, TicketTaskRequest $request): JsonResponse
    {
        $task = $this->ticketService->updateTask($task, $request->validated());
        return response()->json($task);
    }

    public function deleteTask(Ticket $ticket, TicketTask $task): JsonResponse
    {
        $this->ticketService->deleteTask($task);
        return response()->json([], 204);
    }

    public function addItem(Ticket $ticket, TicketTask $task, TicketItemRequest $request): JsonResponse
    {
        $item = $this->ticketService->addItem($task, $request->validated());
        return response()->json($item, 201);
    }

    public function updateItem(Ticket $ticket, TicketTask $task, TicketItem $item, TicketItemRequest $request): JsonResponse
    {
        $item = $this->ticketService->updateItem($item, $request->validated());
        return response()->json($item);
    }

    public function deleteItem(Ticket $ticket, TicketTask $task, TicketItem $item): JsonResponse
    {
        $this->ticketService->deleteItem($item);
        return response()->json([], 204);
    }

    public function end(Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'completed']);
        return response()->json(['message' => 'Ticket ended successfully', 'status' => 'completed']);
    }

    public function reopen(Ticket $ticket): JsonResponse
    {
        $ticket->update(['status' => 'in_progress']);
        return response()->json(['message' => 'Ticket reopened', 'status' => 'in_progress']);
    }

    public function close(Ticket $ticket, Request $request): JsonResponse
    {
        // Here you could handle payment recording if needed
        $ticket->update(['status' => 'completed']); // Or 'closed' if you have that status
        return response()->json(['message' => 'Ticket closed', 'status' => 'completed']);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json([], 204);
    }
}
