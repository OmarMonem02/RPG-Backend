<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Categories\CreateCategoryService;
use App\Services\Categories\DeleteCategoryService;
use App\Services\Categories\UpdateCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CreateCategoryService $createCategoryService,
        private readonly UpdateCategoryService $updateCategoryService,
        private readonly DeleteCategoryService $deleteCategoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:part,accessory,service'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $categories = Category::query()
            ->withCount(['products', 'services'])
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Categories retrieved successfully.',
            'data' => $categories,
        ]);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'message' => 'Category retrieved successfully.',
            'data' => $category->loadCount(['products', 'services']),
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->createCategoryService->execute($request->validated());

        return response()->json([
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->updateCategoryService->execute($category, $request->validated());

        return response()->json([
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->deleteCategoryService->execute($category);

        return response()->json([
            'message' => 'Category deleted successfully.',
        ]);
    }
}
