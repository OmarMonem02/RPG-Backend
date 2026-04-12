<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BikeBlueprintRequest;
use App\Http\Requests\AssignSparePartRequest;
use App\Models\BikeBlueprint;
use App\Models\SparePart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BikeBlueprintController extends Controller
{
    /**
     * Get all bike blueprints with filtering and search.
     * GET /api/bike_blueprints
     * 
     * Query parameters:
     * - search: Search by model
     * - brand_id: Filter by brand
     * - year: Filter by year
     * - per_page: Items per page (default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        $query = BikeBlueprint::query()
            ->search($request->query('search'))
            ->byBrand($request->query('brand_id'))
            ->byYear($request->query('year'));

        $blueprints = $query->with(['brand'])
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($blueprints);
    }

    /**
     * Create a new bike blueprint.
     * POST /api/bike_blueprints
     */
    public function store(BikeBlueprintRequest $request): JsonResponse
    {
        $blueprint = BikeBlueprint::create($request->validated());

        return response()->json($blueprint->load('brand'), 201);
    }

    /**
     * Get a single bike blueprint.
     * GET /api/bike_blueprints/{bike_blueprint}
     */
    public function show(BikeBlueprint $blueprint): JsonResponse
    {
        return response()->json($blueprint->load(['brand']));
    }

    /**
     * Update a bike blueprint.
     * PUT /api/bike_blueprints/{bike_blueprint}
     * PATCH /api/bike_blueprints/{bike_blueprint}
     */
    public function update(BikeBlueprintRequest $request, BikeBlueprint $blueprint): JsonResponse
    {
        $blueprint->update($request->validated());

        return response()->json($blueprint->load('brand'));
    }

    /**
     * Delete a bike blueprint.
     * DELETE /api/bike_blueprints/{bike_blueprint}
     */
    public function destroy(BikeBlueprint $blueprint): JsonResponse
    {
        $blueprint->delete();
        return response()->json([], 204);
    }

    /**
     * Get all spare parts linked to a bike blueprint.
     * GET /api/bike_blueprints/{bike_blueprint}/spare_parts
     * 
     * Query parameters:
     * - search: Search spare parts by name/SKU
     * - brand_id: Filter by spare part brand
     * - category_id: Filter by spare part category
     * - per_page: Items per page (default: 15)
     */
    public function getLinkedSpareParts(Request $request, BikeBlueprint $blueprint): JsonResponse
    {
        $query = $blueprint->spareParts()
            ->search($request->query('search'))
            ->byBrand($request->query('brand_id'))
            ->byCategory($request->query('category_id'));

        $spareParts = $query->with(['category', 'brand'])
            ->paginate((int) $request->query('per_page', 15));

        return response()->json($spareParts);
    }

    /**
     * Assign a spare part to a bike blueprint.
     * POST /api/bike_blueprints/{bike_blueprint}/spare_parts
     * 
     * Request body (single):
     * {
     *   "spare_part_id": 1
     * }
     * 
     * Request body (bulk):
     * {
     *   "spare_part_ids": [1, 2, 3,...]
     * }
     */
    public function assignSpareParts(AssignSparePartRequest $request, BikeBlueprint $blueprint): JsonResponse
    {
        $validated = $request->validated();

        // Single assignment
        if (!empty($validated['spare_part_id'])) {
            $blueprint->spareParts()->syncWithoutDetaching([$validated['spare_part_id']]);
            $sparePart = SparePart::findOrFail($validated['spare_part_id']);

            return response()->json([
                'message' => 'Spare part assigned successfully',
                'bike_blueprint_id' => $blueprint->id,
                'spare_part' => $sparePart->load(['category', 'brand']),
            ], 201);
        }

        // Bulk assignment
        if (!empty($validated['spare_part_ids'])) {
            $blueprint->spareParts()->syncWithoutDetaching($validated['spare_part_ids']);
            $spareParts = SparePart::whereIn('id', $validated['spare_part_ids'])
                ->with(['category', 'brand'])
                ->get();

            return response()->json([
                'message' => 'Spare parts assigned successfully',
                'bike_blueprint_id' => $blueprint->id,
                'spare_parts' => $spareParts,
                'count' => $spareParts->count(),
            ], 201);
        }

        return response()->json(['error' => 'No spare part data provided'], 422);
    }

    /**
     * Remove a spare part from a bike blueprint.
     * DELETE /api/bike_blueprints/{bike_blueprint}/spare_parts/{spare_part}
     */
    public function removeSparePart(BikeBlueprint $blueprint, SparePart $sparePart): JsonResponse
    {
        $blueprint->spareParts()->detach($sparePart->id);

        return response()->json([
            'message' => 'Spare part removed successfully',
            'bike_blueprint_id' => $blueprint->id,
            'spare_part_id' => $sparePart->id,
        ], 204);
    }

    /**
     * Replace all spare parts of a bike blueprint.
     * PUT /api/bike_blueprints/{bike_blueprint}/spare_parts
     * 
     * Request body:
     * {
     *   "spare_part_ids": [1, 2, 3,...]
     * }
     */
    public function replaceSpareParts(Request $request, BikeBlueprint $blueprint): JsonResponse
    {
        $validated = $request->validate([
            'spare_part_ids' => 'required|array',
            'spare_part_ids.*' => 'integer|exists:spare_parts,id',
        ]);

        $blueprint->spareParts()->sync($validated['spare_part_ids']);
        $spareParts = $blueprint->spareParts()->with(['category', 'brand'])->get();

        return response()->json([
            'message' => 'Spare parts updated successfully',
            'bike_blueprint_id' => $blueprint->id,
            'spare_parts' => $spareParts,
            'count' => $spareParts->count(),
        ]);
    }

    /**
     * Get all bikes linked to a bike blueprint.
     * GET /api/bike_blueprints/{bike_blueprint}/bikes
     * 
     * - for_sale: Get bikes for sale
     * - customer_bikes: Get customer bikes
     * - all: Get all bikes (default)
     */
    public function getLinkedBikes(Request $request, BikeBlueprint $blueprint): JsonResponse
    {
        $type = $request->query('type', 'all');

        if ($type === 'for_sale') {
            $bikes = $blueprint->bikesForSale()->paginate();
        } elseif ($type === 'customer') {
            $bikes = $blueprint->customerBikes()->with(['customer'])->paginate();
        } else {
            $forSale = $blueprint->bikesForSale()->count();
            $customerOwned = $blueprint->customerBikes()->count();

            return response()->json([
                'blueprint_id' => $blueprint->id,
                'bikes_for_sale_count' => $forSale,
                'customer_bikes_count' => $customerOwned,
                'total_bikes' => $forSale + $customerOwned,
            ]);
        }

        return response()->json($bikes);
    }

    /**
     * Bulk assign spare parts to multiple blueprints.
     * POST /api/bike_blueprints/bulk/assign-spare-parts
     * 
     * Request body:
     * {
     *   "blueprint_ids": [1, 2, 3],
     *   "spare_part_ids": [5, 6, 7]
     * }
     */
    public function bulkAssignSpareParts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blueprint_ids' => 'required|array|min:1',
            'blueprint_ids.*' => 'integer|exists:bike_blueprints,id',
            'spare_part_ids' => 'required|array|min:1',
            'spare_part_ids.*' => 'integer|exists:spare_parts,id',
        ]);

        $updated = 0;
        foreach ($validated['blueprint_ids'] as $blueprintId) {
            $blueprint = BikeBlueprint::findOrFail($blueprintId);
            $blueprint->spareParts()->syncWithoutDetaching($validated['spare_part_ids']);
            $updated++;
        }

        return response()->json([
            'message' => 'Spare parts assigned to blueprints successfully',
            'blueprints_updated' => $updated,
            'spare_parts_assigned' => count($validated['spare_part_ids']),
        ], 201);
    }
}
