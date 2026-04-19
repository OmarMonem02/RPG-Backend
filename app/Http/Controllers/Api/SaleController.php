<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleCatalogRequest;
use App\Http\Requests\SaleExchangeRequest;
use App\Http\Requests\SaleFilterRequest;
use App\Http\Requests\SaleItemStoreRequest;
use App\Http\Requests\SaleItemUpdateRequest;
use App\Http\Requests\SaleRequest;
use App\Http\Requests\SaleReturnRequest;
use App\Http\Requests\SaleUpdateRequest;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService)
    {
    }

    public function index(SaleFilterRequest $request): JsonResponse
    {
        return response()->json($this->saleService->paginateSales($request->validated()));
    }

    public function store(SaleRequest $request): JsonResponse
    {
        $sale = $this->saleService->create($request->validated(), (int) $request->user()->id);

        return response()->json($sale, 201);
    }

    public function show(Sale $sale): JsonResponse
    {
        return response()->json($this->saleService->getSaleDetails($sale));
    }

    public function adjustments(Request $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->paginateAdjustments($sale, (int) $request->query('per_page', 20))
        );
    }

    public function catalog(SaleCatalogRequest $request): JsonResponse
    {
        return response()->json($this->saleService->catalog($request->validated()));
    }

    public function update(SaleUpdateRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->updateSale($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function addItem(SaleItemStoreRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->addItem($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function updateItem(SaleItemUpdateRequest $request, Sale $sale, SaleItem $saleItem): JsonResponse
    {
        return response()->json(
            $this->saleService->updateItem($sale, $saleItem, $request->validated(), (int) $request->user()->id)
        );
    }

    public function removeItem(Request $request, Sale $sale, SaleItem $saleItem): JsonResponse
    {
        return response()->json(
            $this->saleService->removeItem($sale, $saleItem, (int) $request->user()->id)
        );
    }

    public function returns(SaleReturnRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->processReturn($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function exchanges(SaleExchangeRequest $request, Sale $sale): JsonResponse
    {
        return response()->json(
            $this->saleService->processExchange($sale, $request->validated(), (int) $request->user()->id)
        );
    }

    public function destroy(Sale $sale): JsonResponse
    {
        $this->saleService->delete($sale);

        return response()->json([], 204);
    }
}
