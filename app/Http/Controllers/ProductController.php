<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignProductBikesRequest;
use App\Http\Requests\FilterProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Services\Inventory\AssignProductToBikeService;
use App\Services\Inventory\CalculateProductPriceService;
use App\Services\Inventory\CreateProductService;
use App\Services\Inventory\ListCompatibleProductsService;
use App\Services\Inventory\ListProductsService;
use App\Services\Inventory\UpdateProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ListProductsService $listProductsService,
        private readonly CreateProductService $createProductService,
        private readonly UpdateProductService $updateProductService,
        private readonly AssignProductToBikeService $assignProductToBikeService,
        private readonly CalculateProductPriceService $calculateProductPriceService,
        private readonly ListCompatibleProductsService $listCompatibleProductsService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search'       => ['nullable', 'string', 'max:255'],
            'type'         => ['nullable', 'string', 'in:part,accessory'],
            'category_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id'     => ['nullable', 'integer', 'exists:brands,id'],
            'is_universal' => ['nullable', 'boolean'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = $this->listProductsService->execute(
            search: $request->string('search')->toString() ?: null,
            type: $request->string('type')->toString() ?: null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            brandId: $request->filled('brand_id') ? $request->integer('brand_id') : null,
            isUniversal: $request->filled('is_universal') ? $request->boolean('is_universal') : null,
            perPage: $request->integer('per_page', 15),
        );

        return response()->json([
            'message' => 'Products retrieved successfully.',
            'data'    => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'message' => 'Product retrieved successfully.',
            'data'    => $product->load(['category', 'brand', 'units', 'bikes']),
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->createProductService->execute($request->validated());

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->updateProductService->execute($product, $request->validated());

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    public function assignBikes(AssignProductBikesRequest $request, Product $product): JsonResponse
    {
        $product = $this->assignProductToBikeService->execute($product, $request->validated()['bike_ids']);

        return response()->json([
            'message' => 'Product bike compatibility updated successfully.',
            'data' => $product,
        ]);
    }

    public function compatible(FilterProductsRequest $request): JsonResponse
    {
        $products = $this->listCompatibleProductsService->execute(
            $request->validated('bike_id'),
            $request->validated('brand'),
            $request->validated('model'),
            $request->validated('year'),
            $request->validated('per_page', 15)
        );

        return response()->json([
            'message' => 'Compatible products retrieved successfully.',
            'data' => $products,
        ]);
    }

    public function calculatePrice(Request $request, Product $product): JsonResponse
    {
        $pricing = $this->calculateProductPriceService->execute(
            $product,
            $request->filled('unit_id') ? $request->integer('unit_id') : null
        );

        return response()->json([
            'message' => 'Product price calculated successfully.',
            'data' => $pricing,
        ]);
    }
}
