<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignProductBikesRequest;
use App\Http\Requests\FilterProductsRequest;
use App\Http\Resources\ProductInventoryResource;
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
            'in_stock'     => ['nullable', 'boolean'],
            'low_stock'    => ['nullable', 'boolean'],
            'has_units'    => ['nullable', 'boolean'],
            'sort_by'      => ['nullable', 'string', 'in:id,name,qty,selling_price,created_at,updated_at'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $products = $this->listProductsService->execute(
            search: $request->string('search')->toString() ?: null,
            type: $request->string('type')->toString() ?: null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            brandId: $request->filled('brand_id') ? $request->integer('brand_id') : null,
            isUniversal: $request->filled('is_universal') ? $request->boolean('is_universal') : null,
            inStock: $request->filled('in_stock') ? $request->boolean('in_stock') : null,
            lowStock: $request->filled('low_stock') ? $request->boolean('low_stock') : null,
            hasUnits: $request->filled('has_units') ? $request->boolean('has_units') : null,
            sortBy: $request->string('sort_by')->toString() ?: 'id',
            sortDirection: $request->string('sort_direction')->toString() ?: 'desc',
            perPage: $request->integer('per_page', 15),
        );

        $products->setCollection(collect(
            ProductInventoryResource::collection($products->getCollection())->resolve()
        ));

        return response()->json([
            'message' => 'Products retrieved successfully.',
            'data'    => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->successResponse(
            'Product retrieved successfully.',
            new ProductInventoryResource($product->load(['category', 'brand', 'units', 'bikes']))
        );
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

        $products->setCollection(collect(
            ProductInventoryResource::collection($products->getCollection())->resolve()
        ));

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
