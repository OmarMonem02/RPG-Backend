<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReturnRequest;
use App\Models\Sale;
use App\Services\Returns\HandleReturnService;
use App\Services\Returns\ListSaleReturnsService;
use Illuminate\Http\JsonResponse;

class ReturnController extends Controller
{
    public function __construct(
        private readonly HandleReturnService $handleReturnService,
        private readonly ListSaleReturnsService $listSaleReturnsService,
    ) {}

    public function index(Sale $sale): JsonResponse
    {
        return response()->json([
            'message' => 'Sale returns retrieved successfully.',
            'data' => $this->listSaleReturnsService->execute($sale),
        ]);
    }

    public function store(StoreReturnRequest $request, Sale $sale): JsonResponse
    {
        $return = $this->handleReturnService->execute($sale, $request->validated());

        return response()->json([
            'message' => 'Return processed successfully.',
            'data' => $return,
        ], 201);
    }
}
