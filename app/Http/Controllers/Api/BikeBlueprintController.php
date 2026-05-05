<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignSparePartRequest;
use App\Http\Requests\BikeBlueprintRequest;
use App\Models\BikeBlueprint;
use App\Models\SparePart;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BikeBlueprintController extends Controller
{
    private const LIST_TTL_SECONDS = 1800;

    private const DETAIL_TTL_SECONDS = 3600;

    private const BLUEPRINT_TAG = 'blueprints';

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
        $cacheKey = ApiCache::listKey(self::BLUEPRINT_TAG, $request);
        $tags = [self::BLUEPRINT_TAG];
        $cacheHit = ApiCache::has($cacheKey, $tags);
        $blueprints = ApiCache::remember($cacheKey, self::LIST_TTL_SECONDS, $tags, function () use ($request) {
            $query = BikeBlueprint::query()
                ->search($request->query('search'))
                ->byBrand($request->query('brand_id'))
                ->byYear($request->query('year'));

            return $query->with(['brand'])
                ->paginate((int) $request->query('per_page', 20));
        });

        return response()
            ->json($blueprints)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
    }

    /**
     * Create a new bike blueprint.
     * POST /api/bike_blueprints
     */
    public function store(BikeBlueprintRequest $request): JsonResponse
    {
        $blueprint = BikeBlueprint::create($request->validated());
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'bikes']);

        return response()->json($blueprint->load('brand'), 201);
    }

    /**
     * Get a single bike blueprint.
     * GET /api/bike_blueprints/{bike_blueprint}
     */
    public function show(BikeBlueprint $bike_blueprint): JsonResponse
    {
        $cacheKey = ApiCache::detailKey(self::BLUEPRINT_TAG, $bike_blueprint->id);
        $tags = [self::BLUEPRINT_TAG];
        $cacheHit = ApiCache::has($cacheKey, $tags);
        $payload = ApiCache::remember($cacheKey, self::DETAIL_TTL_SECONDS, $tags, fn () => $bike_blueprint->load(['brand']));

        return response()
            ->json($payload)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
    }

    /**
     * Update a bike blueprint.
     * PUT /api/bike_blueprints/{bike_blueprint}
     * PATCH /api/bike_blueprints/{bike_blueprint}
     */
    public function update(BikeBlueprintRequest $request, BikeBlueprint $bike_blueprint): JsonResponse
    {
        $bike_blueprint->update($request->validated());
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'bikes']);

        return response()->json($bike_blueprint->load('brand'));
    }

    /**
     * Delete a bike blueprint.
     * DELETE /api/bike_blueprints/{bike_blueprint}
     */
    public function destroy(BikeBlueprint $bike_blueprint): Response|JsonResponse
    {
        $bike_blueprint->delete();
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'bikes', 'spare_parts']);

        return response()->noContent();
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
    public function getLinkedSpareParts(Request $request, BikeBlueprint $bike_blueprint): JsonResponse
    {
        $cacheKey = sprintf(
            '%s:linked_spare_parts:%s:%s',
            self::BLUEPRINT_TAG,
            $bike_blueprint->id,
            ApiCache::hashQuery($request->query())
        );
        $tags = [self::BLUEPRINT_TAG, 'spare_parts'];
        $cacheHit = ApiCache::has($cacheKey, $tags);
        $spareParts = ApiCache::remember($cacheKey, self::LIST_TTL_SECONDS, $tags, function () use ($request, $bike_blueprint) {
            $query = $bike_blueprint->spareParts()
                ->search($request->query('search'))
                ->byBrand($request->query('brand_id'))
                ->byCategory($request->query('category_id'));

            return $query->with(['category', 'brand'])
                ->paginate((int) $request->query('per_page', 15));
        });

        return response()
            ->json($spareParts)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
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
    public function assignSpareParts(AssignSparePartRequest $request, BikeBlueprint $bike_blueprint): JsonResponse
    {
        $validated = $request->validated();

        // Single assignment
        if (! empty($validated['spare_part_id'])) {
            $bike_blueprint->spareParts()->syncWithoutDetaching([$validated['spare_part_id']]);
            $sparePart = SparePart::findOrFail($validated['spare_part_id']);
            ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

            return response()->json([
                'message' => 'Spare part assigned successfully',
                'bike_blueprint_id' => $bike_blueprint->id,
                'spare_part_id' => $sparePart->id,
                'spare_part' => $sparePart->load(['category', 'brand']),
            ], 201);
        }

        // Bulk assignment
        if (! empty($validated['spare_part_ids'])) {
            $bike_blueprint->spareParts()->syncWithoutDetaching($validated['spare_part_ids']);
            $spareParts = SparePart::whereIn('id', $validated['spare_part_ids'])
                ->with(['category', 'brand'])
                ->get();
            ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

            return response()->json([
                'message' => 'Spare parts assigned successfully',
                'bike_blueprint_id' => $bike_blueprint->id,
                'spare_parts' => $spareParts,
                'count' => $spareParts->count(),
            ], 201);
        }

        if (! empty($validated['spare_part_data'])) {
            $sparePart = SparePart::create($validated['spare_part_data'] + [
                'max_discount_value' => 0,
            ]);
            $bike_blueprint->spareParts()->syncWithoutDetaching([$sparePart->id]);
            ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

            return response()->json([
                'message' => 'Spare part created and assigned successfully',
                'bike_blueprint_id' => $bike_blueprint->id,
                'spare_part' => $sparePart->load(['category', 'brand']),
            ], 201);
        }

        return response()->json(['error' => 'No spare part data provided'], 422);
    }

    /**
     * Remove a spare part from a bike blueprint.
     * DELETE /api/bike_blueprints/{bike_blueprint}/spare_parts/{spare_part}
     */
    public function removeSparePart(BikeBlueprint $bike_blueprint, SparePart $spare_part): JsonResponse
    {
        $bike_blueprint->spareParts()->detach($spare_part->id);
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

        return response()->json([
            'message' => 'Spare part removed successfully',
            'bike_blueprint_id' => $bike_blueprint->id,
            'spare_part_id' => $spare_part->id,
        ]);
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
    public function replaceSpareParts(Request $request, BikeBlueprint $bike_blueprint): JsonResponse
    {
        $validated = $request->validate([
            'spare_part_ids' => 'required|array',
            'spare_part_ids.*' => 'integer|exists:spare_parts,id',
        ]);

        $bike_blueprint->spareParts()->sync($validated['spare_part_ids']);
        $spareParts = $bike_blueprint->spareParts()->with(['category', 'brand'])->get();
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

        return response()->json([
            'message' => 'Spare parts updated successfully',
            'bike_blueprint_id' => $bike_blueprint->id,
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
    public function getLinkedBikes(Request $request, BikeBlueprint $bike_blueprint): JsonResponse
    {
        $cacheKey = sprintf(
            '%s:linked_bikes:%s:%s',
            self::BLUEPRINT_TAG,
            $bike_blueprint->id,
            ApiCache::hashQuery($request->query())
        );
        $tags = [self::BLUEPRINT_TAG, 'bikes'];
        $cacheHit = ApiCache::has($cacheKey, $tags);

        $payload = ApiCache::remember($cacheKey, self::LIST_TTL_SECONDS, $tags, function () use ($request, $bike_blueprint) {
            $type = $request->query('type', 'all');

            if ($type === 'for_sale') {
                return $bike_blueprint->bikesForSale()->paginate();
            } elseif ($type === 'customer') {
                return $bike_blueprint->customerBikes()->with(['customer'])->paginate();
            }

            $forSale = $bike_blueprint->bikesForSale()->count();
            $customerOwned = $bike_blueprint->customerBikes()->count();

            return [
                'blueprint_id' => $bike_blueprint->id,
                'bikes_for_sale_count' => $forSale,
                'customer_bikes_count' => $customerOwned,
                'total_bikes' => $forSale + $customerOwned,
            ];
        });

        return response()
            ->json($payload)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
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
        ApiCache::invalidateTags([self::BLUEPRINT_TAG, 'spare_parts']);

        return response()->json([
            'message' => 'Spare parts assigned to blueprints successfully',
            'blueprints_updated' => $updated,
            'spare_parts_assigned' => count($validated['spare_part_ids']),
        ], 201);
    }
}
