<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignItemRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Services\Tickets\AddTaskService;
use App\Services\Tickets\AssignItemToTaskService;
use App\Services\Tickets\DeleteTaskService;
use App\Services\Tickets\RemoveItemFromTaskService;
use App\Services\Tickets\UpdateTaskService;
use Illuminate\Http\JsonResponse;

class TaskController extends Controller
{
    public function __construct(
        private readonly AddTaskService $addTaskService,
        private readonly UpdateTaskService $updateTaskService,
        private readonly DeleteTaskService $deleteTaskService,
        private readonly AssignItemToTaskService $assignItemToTaskService,
        private readonly RemoveItemFromTaskService $removeItemFromTaskService,
    ) {}

    public function store(StoreTaskRequest $request, Ticket $ticket): JsonResponse
    {
        $task = $this->addTaskService->execute($ticket, $request->validated());

        return response()->json([
            'message' => 'Task added successfully.',
            'data' => $task,
        ], 201);
    }

    public function update(StoreTaskRequest $request, Ticket $ticket, TicketTask $task): JsonResponse
    {
        $task = $this->updateTaskService->execute($ticket, $task, $request->validated());

        return response()->json([
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    public function destroy(Ticket $ticket, TicketTask $task): JsonResponse
    {
        $this->deleteTaskService->execute($ticket, $task);

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    public function assignItem(AssignItemRequest $request, Ticket $ticket, TicketTask $task): JsonResponse
    {
        $item = $this->assignItemToTaskService->execute($ticket, $task, $request->validated());

        return response()->json([
            'message' => 'Item assigned to task successfully.',
            'data' => $item,
        ], 201);
    }

    public function removeItem(Ticket $ticket, TicketTask $task, TicketItem $item): JsonResponse
    {
        $this->removeItemFromTaskService->execute($ticket, $task, $item);

        return response()->json([
            'message' => 'Item removed from task successfully.',
        ]);
    }
}
