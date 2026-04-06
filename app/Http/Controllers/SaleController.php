<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPaymentRequest;
use App\Http\Requests\AddSaleItemRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Sale;
use App\Services\Sales\AddItemToSaleService;
use App\Services\Sales\AddPaymentService;
use App\Services\Sales\CompleteSaleService;
use App\Services\Sales\CreateSaleService;
use App\Services\Sales\ReturnSaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(
        private readonly CreateSaleService $createSaleService,
        private readonly AddItemToSaleService $addItemToSaleService,
        private readonly AddPaymentService $addPaymentService,
        private readonly CompleteSaleService $completeSaleService,
        private readonly ReturnSaleService $returnSaleService,
    ) {
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->createSaleService->execute($request->validated());

        return response()->json([
            'message' => 'Sale created successfully.',
            'data' => $sale,
        ], 201);
    }

    public function addItem(AddSaleItemRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->addItemToSaleService->execute($sale, $request->validated());

        return response()->json([
            'message' => 'Item added to sale successfully.',
            'data' => $sale,
        ]);
    }

    public function addPayment(AddPaymentRequest $request, Sale $sale): JsonResponse
    {
        $sale = $this->addPaymentService->execute($sale, $request->validated());

        return response()->json([
            'message' => 'Payment added successfully.',
            'data' => $sale,
        ]);
    }

    public function complete(Sale $sale): JsonResponse
    {
        $sale = $this->completeSaleService->execute($sale);

        return response()->json([
            'message' => 'Sale completed successfully.',
            'data' => $sale,
        ]);
    }

    public function returnSale(Sale $sale): JsonResponse
    {
        $sale = $this->returnSaleService->execute($sale);

        return response()->json([
            'message' => 'Sale returned successfully.',
            'data' => $sale,
        ]);
    }
}
