<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogFilterRequest;
use App\Models\Log;
use App\Services\Logs\LogActivityService;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    public function __construct(
        private readonly LogActivityService $logActivityService,
    ) {
    }

    public function index(LogFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $logs = Log::query()
            ->with('user:id,name,email')
            ->when(isset($validated['user_id']), fn ($query) => $query->where('user_id', $validated['user_id']))
            ->when(isset($validated['action']), fn ($query) => $query->where('action', $validated['action']))
            ->when(isset($validated['entity_type']), fn ($query) => $query->where('entity_type', $validated['entity_type']))
            ->when(isset($validated['from_date']), fn ($query) => $query->whereDate('created_at', '>=', $validated['from_date']))
            ->when(isset($validated['to_date']), fn ($query) => $query->whereDate('created_at', '<=', $validated['to_date']))
            ->latest()
            ->paginate(25);

        return response()->json([
            'message' => 'Logs retrieved successfully.',
            'data' => $logs,
        ]);
    }

    public function show(Log $log): JsonResponse
    {
        $log->load('user:id,name,email');

        return response()->json([
            'message' => 'Log details retrieved successfully.',
            'data' => [
                'log' => $log,
                'diff' => $this->logActivityService->buildDiff($log),
            ],
        ]);
    }
}
