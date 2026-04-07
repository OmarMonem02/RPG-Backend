<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\Brands\CreateBrandService;
use App\Services\Brands\DeleteBrandService;
use App\Services\Brands\UpdateBrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct(
        private readonly CreateBrandService $createBrandService,
        private readonly UpdateBrandService $updateBrandService,
        private readonly DeleteBrandService $deleteBrandService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $brands = Brand::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Brands retrieved successfully.',
            'data' => $brands,
        ]);
    }

    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'message' => 'Brand retrieved successfully.',
            'data' => $brand->loadCount('products'),
        ]);
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->createBrandService->execute($request->validated());

        return response()->json([
            'message' => 'Brand created successfully.',
            'data' => $brand,
        ], 201);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->updateBrandService->execute($brand, $request->validated());

        return response()->json([
            'message' => 'Brand updated successfully.',
            'data' => $brand,
        ]);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->deleteBrandService->execute($brand);

        return response()->json([
            'message' => 'Brand deleted successfully.',
        ]);
    }
}
