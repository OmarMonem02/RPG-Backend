<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductUnitRequest;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Services\Inventory\ManageProductUnitService;
use Illuminate\Http\JsonResponse;

class ProductUnitController extends Controller
{
    public function __construct(
        private readonly ManageProductUnitService $manageProductUnitService,
    ) {
    }

    public function store(StoreProductUnitRequest $request, Product $product): JsonResponse
    {
        $unit = $this->manageProductUnitService->create($product, $request->validated());

        return response()->json([
            'message' => 'Product unit created successfully.',
            'data' => $unit,
        ], 201);
    }

    public function update(StoreProductUnitRequest $request, Product $product, ProductUnit $unit): JsonResponse
    {
        $unit = $this->manageProductUnitService->update($product, $unit, $request->validated());

        return response()->json([
            'message' => 'Product unit updated successfully.',
            'data' => $unit,
        ]);
    }

    public function destroy(Product $product, ProductUnit $unit): JsonResponse
    {
        $this->manageProductUnitService->delete($product, $unit);

        return response()->json([
            'message' => 'Product unit deleted successfully.',
        ]);
    }
}
