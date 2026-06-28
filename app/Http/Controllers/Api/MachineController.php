<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MachineRequest;
use App\Models\Machine;
use App\Services\MachineDocumentService;
use App\Support\CaseInsensitiveLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineController extends Controller
{
    public function __construct(private readonly MachineDocumentService $documentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'category' => ['nullable', Rule::in(Machine::CATEGORIES)],
            'status' => ['nullable', Rule::in(Machine::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Machine::query()
            ->with('documents')
            ->latest('id');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($inner) use ($search) {
                CaseInsensitiveLike::where($inner, 'name', $search);
                CaseInsensitiveLike::orWhere($inner, 'serial_number', $search);
                CaseInsensitiveLike::orWhere($inner, 'location', $search);
                CaseInsensitiveLike::orWhere($inner, 'notes', $search);
            });
        }

        return response()->json($query->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function store(MachineRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $documents = $validated['documents'] ?? [];
        unset($validated['documents'], $validated['remove_document_ids']);

        $machine = Machine::create($validated);
        $this->documentService->attachDocuments($machine, $documents);

        return response()->json($machine->load('documents'), 201);
    }

    public function show(Machine $machine): JsonResponse
    {
        return response()->json($machine->load('documents'));
    }

    public function update(MachineRequest $request, Machine $machine): JsonResponse
    {
        $validated = $request->validated();
        $documents = $validated['documents'] ?? [];
        $removeIds = $validated['remove_document_ids'] ?? [];
        unset($validated['documents'], $validated['remove_document_ids']);

        $machine->fill($validated);
        $machine->save();

        $this->documentService->removeDocuments($machine, $removeIds);
        $this->documentService->attachDocuments($machine, $documents);

        return response()->json($machine->load('documents'));
    }

    public function destroy(Machine $machine): JsonResponse
    {
        $this->documentService->deleteAllDocuments($machine);
        $machine->delete();

        return response()->json([], 204);
    }
}
