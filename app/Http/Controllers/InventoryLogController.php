<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryLogIndexRequest;
use App\Http\Resources\StockLogResource;
use App\Services\Inventory\ListInventoryLogsService;
use Illuminate\Http\JsonResponse;

class InventoryLogController extends Controller
{
    public function __construct(
        private readonly ListInventoryLogsService $listInventoryLogsService,
    ) {}

    public function index(InventoryLogIndexRequest $request): JsonResponse
    {
        $logs = $this->listInventoryLogsService->execute($request->validated());

        return $this->successResponse('Inventory logs retrieved successfully.', [
            'items' => StockLogResource::collection($logs->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
