<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketDiscountRequest;
use App\Http\Requests\TicketFilterRequest;
use App\Http\Requests\TicketItemRequest;
use App\Http\Requests\TicketRequest;
use App\Http\Requests\TicketTaskRequest;
use App\Http\Requests\UpdateTicketNotesRequest;
use App\Exports\UnstoredTicketItemsExport;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Services\TicketQueryService;
use App\Services\TicketService;
use App\Support\Export\ExportColumnResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
        private readonly TicketQueryService $ticketQueryService,
        private readonly ExportColumnResolver $columnResolver,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(Ticket::with(Ticket::detailRelations())->paginate(20));
    }

    public function export(TicketFilterRequest $request): BinaryFileResponse|JsonResponse
    {
        $filters = $request->validated();
        $query = $this->ticketQueryService->exportUnstoredItemsQuery($filters);
        $count = (clone $query)->count();

        if ($count > 50_000) {
            return response()->json([
                'message' => 'Too many rows match these filters. Narrow your filters and try again. Maximum allowed is 50,000 rows.',
                'matched_count' => $count,
            ], 422);
        }

        $format = match (strtolower((string) $request->query('format', 'xlsx'))) {
            'csv' => ExcelFormat::CSV,
            default => ExcelFormat::XLSX,
        };

        $suffix = $format === ExcelFormat::CSV ? '.csv' : '.xlsx';
        $filename = 'unstored_ticket_items_' . now()->format('Ymd_His') . $suffix;
        $columnKeys = $this->columnResolver->resolve('unstored_ticket_items', $request->query('columns'));

        return Excel::download(
            new UnstoredTicketItemsExport($query, $columnKeys),
            $filename,
            $format,
        );
    }

    public function store(TicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create($request->validated(), (int) $request->user()->id);

        return response()->json($ticket, 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($ticket->load(Ticket::detailRelations()));
    }

    public function updateStatus(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed'])],
        ]);
        $ticket->update(['status' => $data['status']]);

        return response()->json($ticket);
    }

    public function updateNotes(Ticket $ticket, UpdateTicketNotesRequest $request): JsonResponse
    {
        if ($ticket->status === 'closed') {
            return response()->json([
                'message' => 'Cannot edit notes on a closed ticket.',
            ], 422);
        }

        $ticket->update($request->validated());

        return response()->json($ticket->load(Ticket::detailRelations()));
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
        $item = $this->ticketService->addItem($task, $request->validated(), $request->user());
        return response()->json($item, 201);
    }

    public function updateItem(Ticket $ticket, TicketTask $task, TicketItem $item, TicketItemRequest $request): JsonResponse
    {
        $item = $this->ticketService->updateItem($item, $request->validated(), $request->user());
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

    public function reopen(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'admin_password' => ['nullable', 'string'],
        ]);

        $ticket = $this->ticketService->reopen(
            $ticket,
            $request->user(),
            $data['admin_password'] ?? null,
        );

        return response()->json([
            'message' => 'Ticket reopened',
            'status' => $ticket->status,
            'ticket' => $ticket->load(Ticket::detailRelations()),
        ]);
    }

    public function updateDiscount(Ticket $ticket, TicketDiscountRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->updateDiscount(
            $ticket,
            $request->user(),
            $request->validated(),
        );

        return response()->json($ticket);
    }

    public function close(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:64'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $ticket = $this->ticketService->close($ticket, $data, $request->user());

        return response()->json([
            'message' => 'Ticket closed',
            'status' => $ticket->status,
            'ticket' => $ticket->load(Ticket::detailRelations()),
        ]);
    }

    public function recordPayment(Ticket $ticket, Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'string', 'max:64'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
            'admin_password' => ['nullable', 'string'],
        ]);

        $ticket = $this->ticketService->recordPayment($ticket, $data, $request->user());

        return response()->json([
            'message' => 'Payment recorded',
            'status' => $ticket->status,
            'ticket' => $ticket->load(Ticket::detailRelations()),
        ]);
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json([], 204);
    }
}
