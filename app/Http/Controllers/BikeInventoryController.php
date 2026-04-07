<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBikeInventoryRequest;
use App\Http\Requests\UpdateBikeInventoryRequest;
use App\Models\BikeInventory;
use App\Services\BikeInventory\CreateBikeInventoryService;
use App\Services\BikeInventory\DeleteBikeInventoryService;
use App\Services\BikeInventory\UpdateBikeInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BikeInventoryController extends Controller
{
    public function __construct(
        private readonly CreateBikeInventoryService $createBikeInventoryService,
        private readonly UpdateBikeInventoryService $updateBikeInventoryService,
        private readonly DeleteBikeInventoryService $deleteBikeInventoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'bike_id' => ['nullable', 'integer', 'exists:bikes,id'],
            'type' => ['nullable', 'string', 'in:owned,consignment'],
            'sold' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bikeInventory = BikeInventory::query()
            ->with(['bike', 'saleItems.sale'])
            ->when($request->filled('bike_id'), fn ($query) => $query->where('bike_id', $request->integer('bike_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('sold'), function ($query) use ($request): void {
                if ($request->boolean('sold')) {
                    $query->whereHas('saleItems');
                } else {
                    $query->whereDoesntHave('saleItems');
                }
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Bike inventory retrieved successfully.',
            'data' => $bikeInventory,
        ]);
    }

    public function show(BikeInventory $bikeInventory): JsonResponse
    {
        return response()->json([
            'message' => 'Bike inventory retrieved successfully.',
            'data' => $bikeInventory->load(['bike', 'saleItems.sale']),
        ]);
    }

    public function store(StoreBikeInventoryRequest $request): JsonResponse
    {
        $bikeInventory = $this->createBikeInventoryService->execute($request->validated());

        return response()->json([
            'message' => 'Bike inventory created successfully.',
            'data' => $bikeInventory,
        ], 201);
    }

    public function update(UpdateBikeInventoryRequest $request, BikeInventory $bikeInventory): JsonResponse
    {
        $bikeInventory = $this->updateBikeInventoryService->execute($bikeInventory, $request->validated());

        return response()->json([
            'message' => 'Bike inventory updated successfully.',
            'data' => $bikeInventory,
        ]);
    }

    public function destroy(BikeInventory $bikeInventory): JsonResponse
    {
        $this->deleteBikeInventoryService->execute($bikeInventory);

        return response()->json([
            'message' => 'Bike inventory deleted successfully.',
        ]);
    }
}
