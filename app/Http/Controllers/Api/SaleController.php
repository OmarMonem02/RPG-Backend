<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json(Sale::with(['items', 'customer', 'user', 'seller', 'paymentMethod'])->paginate(20));
    }

    public function store(SaleRequest $request): JsonResponse
    {
        $sale = $this->saleService->create($request->validated(), (int) $request->user()->id);

        return response()->json($sale, 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($sale->load(['items', 'customer', 'user', 'seller', 'paymentMethod']));
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $sale->delete();

        return response()->json([], 204);
    }
}
