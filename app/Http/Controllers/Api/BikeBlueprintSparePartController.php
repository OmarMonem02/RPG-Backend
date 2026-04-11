<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BikeBlueprintSparePartRequest;
use App\Models\BikeBlueprint;
use App\Models\SparePart;
use App\Services\BikeBlueprintSparePartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BikeBlueprintSparePartController extends Controller
{
    public function __construct(private BikeBlueprintSparePartService $service) {}

    /**
     * Get all spare parts for a bike blueprint.
     * GET /api/bike_blueprints/{bikeBlueprint}/spare_parts
     */
    public function index(Request $request, BikeBlueprint $bikeBlueprint): JsonResponse
    {
        $filters = $request->only(['category_id', 'brand_id', 'search']);
        $perPage = (int) $request->query('per_page', 15);

        $spareParts = $this->service->getSparePartsByBlueprint($bikeBlueprint, $filters, $perPage);

        return response()->json($spareParts);
    }

    /**
     * Assign spare parts to a bike blueprint.
     * Supports both single assignment and bulk assignment.
     * POST /api/bike_blueprints/{bikeBlueprint}/spare_parts
     *
     * Request body:
     * {
     *   "spare_part_id": 1,        // OR
     *   "spare_part_ids": [1, 2, 3] // for bulk
     * }
     */
    public function store(BikeBlueprintSparePartRequest $request, BikeBlueprint $bikeBlueprint): JsonResponse
    {
        $validated = $request->validated();

        // Single assignment
        if (! empty($validated['spare_part_id'])) {
            $result = $this->service->assignSparePart($bikeBlueprint, $validated['spare_part_id']);

            return response()->json($result->load('sparePart'), 201);
        }

        // Bulk assignment
        if (! empty($validated['spare_part_ids'])) {
            $results = $this->service->assignSpareParts($bikeBlueprint, $validated['spare_part_ids']);

            return response()->json(
                collect($results)->map(fn ($item) => $item->load('sparePart'))->toArray(),
                201
            );
        }

        // Create and assign workflow
        if (! empty($validated['spare_part_data'])) {
            $result = $this->service->assignAndCreateSparePart($bikeBlueprint, $validated['spare_part_data']);

            return response()->json($result->load('sparePart'), 201);
        }

        return response()->json(['error' => 'No spare part data provided'], 422);
    }

    /**
     * Remove a spare part from a bike blueprint.
     * DELETE /api/bike_blueprints/{bikeBlueprint}/spare_parts/{sparePart}
     */
    public function destroy(BikeBlueprint $bikeBlueprint, SparePart $sparePart): JsonResponse
    {
        $deleted = $this->service->removeSparePart($bikeBlueprint, $sparePart->id);

        if (! $deleted) {
            return response()->json(['error' => 'Spare part not found in this blueprint'], 404);
        }

        return response()->json([], 204);
    }
}
