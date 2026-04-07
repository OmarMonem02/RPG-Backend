<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\BulkUpdateRequest;
use App\Http\Requests\ImportProductsRequest;
use App\Http\Requests\UpdateExchangeRateRequest;
use App\Models\Product;
use App\Services\Inventory\AdjustStockService;
use App\Services\Inventory\BulkUpdateProductsService;
use App\Services\Inventory\ExportProductsService;
use App\Services\Inventory\ImportProductsService;
use App\Services\Inventory\UpdateExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly BulkUpdateProductsService $bulkUpdateProductsService,
        private readonly ImportProductsService $importProductsService,
        private readonly ExportProductsService $exportProductsService,
        private readonly UpdateExchangeRateService $updateExchangeRateService,
        private readonly AdjustStockService $adjustStockService,
    ) {}

    public function bulkUpdate(BulkUpdateRequest $request): JsonResponse
    {
        $count = $this->bulkUpdateProductsService->execute(
            $request->validated()['product_ids'],
            $request->validated()['attributes']
        );

        return response()->json([
            'message' => 'Products updated successfully.',
            'data' => ['updated_count' => $count],
        ]);
    }

    public function import(ImportProductsRequest $request): JsonResponse
    {
        $result = $this->importProductsService->execute(
            $request->file('file'),
            $request->validated()['mode'] ?? 'upsert'
        );

        return response()->json([
            'message' => 'Products imported successfully.',
            'data' => $result,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return $this->exportProductsService->export($request->string('format')->toString() ?: 'csv');
    }

    public function template(Request $request): StreamedResponse
    {
        return $this->exportProductsService->template($request->string('format')->toString() ?: 'csv');
    }

    public function updateExchangeRate(UpdateExchangeRateRequest $request): JsonResponse
    {
        $rate = $this->updateExchangeRateService->execute(
            (float) $request->validated()['rate'],
            $request->validated()['currency'] ?? 'USD'
        );

        return response()->json([
            'message' => 'Exchange rate updated successfully.',
            'data' => $rate,
        ]);
    }

    public function adjustStock(AdjustStockRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = $this->adjustStockService->execute(
            $validated['product_id'],
            (float) $request->validated()['qty'],
            $validated['change_type'],
            $validated['reference_type'],
            $validated['reference_id'] ?? null,
            $validated['unit_id'] ?? null
        );

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'data' => $product,
        ]);
    }

    public function adjustProductStock(AdjustStockRequest $request, Product $product): JsonResponse
    {
        $request->merge(['product_id' => $product->id]);

        return $this->adjustStock($request);
    }
}
