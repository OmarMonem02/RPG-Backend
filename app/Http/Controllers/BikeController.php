<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBikeRequest;
use App\Http\Requests\UpdateBikeRequest;
use App\Models\Bike;
use App\Services\Bikes\CreateBikeService;
use App\Services\Bikes\DeleteBikeService;
use App\Services\Bikes\UpdateBikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BikeController extends Controller
{
    public function __construct(
        private readonly CreateBikeService $createBikeService,
        private readonly UpdateBikeService $updateBikeService,
        private readonly DeleteBikeService $deleteBikeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $bikes = Bike::query()
            ->withCount(['products', 'inventoryBikes'])
            ->when($request->filled('brand'), fn ($query) => $query->where('brand', 'like', '%'.$request->string('brand').'%'))
            ->when($request->filled('model'), fn ($query) => $query->where('model', 'like', '%'.$request->string('model').'%'))
            ->when($request->filled('year'), fn ($query) => $query->where('year', $request->integer('year')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');

                $query->where(function ($query) use ($search): void {
                    $query->where('brand', 'like', '%'.$search.'%')
                        ->orWhere('model', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('year')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => 'Bike blueprints retrieved successfully.',
            'data' => $bikes,
        ]);
    }

    public function show(Bike $bike): JsonResponse
    {
        return response()->json([
            'message' => 'Bike blueprint retrieved successfully.',
            'data' => $bike->loadCount(['products', 'inventoryBikes']),
        ]);
    }

    public function store(StoreBikeRequest $request): JsonResponse
    {
        $bike = $this->createBikeService->execute($request->validated());

        return response()->json([
            'message' => 'Bike blueprint created successfully.',
            'data' => $bike,
        ], 201);
    }

    public function update(UpdateBikeRequest $request, Bike $bike): JsonResponse
    {
        $bike = $this->updateBikeService->execute($bike, $request->validated());

        return response()->json([
            'message' => 'Bike blueprint updated successfully.',
            'data' => $bike,
        ]);
    }

    public function destroy(Bike $bike): JsonResponse
    {
        $this->deleteBikeService->execute($bike);

        return response()->json([
            'message' => 'Bike blueprint deleted successfully.',
        ]);
    }
}
