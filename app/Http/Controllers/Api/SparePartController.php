<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SparePartRequest;
use App\Models\SparePart;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
    private const LIST_TTL_SECONDS = 1800;
    private const DETAIL_TTL_SECONDS = 3600;
    private const TAGS = ['spare_parts'];

    /**
     * Get all spare parts with filtering and search.
     * GET /api/spare_parts
     * 
     * Query parameters:
     * - search: Search by name, SKU, or part_number
     * - brand_id: Filter by brand
     * - category_id: Filter by category
     * - low_stock: Show only low stock items (true/false)
     * - per_page: Items per page (default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = ApiCache::listKey('spare_parts', $request);
        $cacheHit = ApiCache::has($cacheKey, self::TAGS);

        $spareParts = ApiCache::remember($cacheKey, self::LIST_TTL_SECONDS, self::TAGS, function () use ($request) {
            $query = SparePart::query()
                ->search($request->query('search'))
                ->byBrand($request->query('brand_id'))
                ->byCategory($request->query('category_id'));

            if ($request->boolean('low_stock')) {
                $query->lowStock();
            }

            return $query->with(['category', 'brand', 'bikeBlueprints'])
                ->paginate((int) $request->query('per_page', 20));
        });

        return response()
            ->json($spareParts)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
    }

    /**
     * Create a new spare part and optionally assign to bike blueprints.
     * POST /api/spare_parts
     */
    public function store(SparePartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $bikeBlueprintIds = $validated['bike_blueprint_ids'] ?? [];
        unset($validated['bike_blueprint_ids']);

        $sparePart = SparePart::create($validated);

        if (!empty($bikeBlueprintIds)) {
            $sparePart->bikeBlueprints()->attach($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if (!empty($bikeBlueprintIds)) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($sparePart->load(['category', 'brand', 'bikeBlueprints']), 201);
    }

    /**
     * Get a single spare part.
     * GET /api/spare_parts/{spare_part}
     */
    public function show(SparePart $sparePart): JsonResponse
    {
        $cacheKey = ApiCache::detailKey('spare_parts', $sparePart->id);
        $cacheHit = ApiCache::has($cacheKey, self::TAGS);

        $payload = ApiCache::remember($cacheKey, self::DETAIL_TTL_SECONDS, self::TAGS, fn () => $sparePart->load(['category', 'brand', 'bikeBlueprints']));

        return response()
            ->json($payload)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
    }

    /**
     * Update a spare part.
     * PUT /api/spare_parts/{spare_part}
     * PATCH /api/spare_parts/{spare_part}
     */
    public function update(SparePartRequest $request, SparePart $sparePart): JsonResponse
    {
        $validated = $request->validated();
        $bikeBlueprintIds = $validated['bike_blueprint_ids'] ?? null;
        unset($validated['bike_blueprint_ids']);

        $sparePart->update($validated);

        if ($bikeBlueprintIds !== null) {
            $sparePart->bikeBlueprints()->sync($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if ($bikeBlueprintIds !== null) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($sparePart->load(['category', 'brand', 'bikeBlueprints']));
    }

    /**
     * Delete a spare part.
     * DELETE /api/spare_parts/{spare_part}
     */
    public function destroy(SparePart $sparePart): JsonResponse
    {
        $sparePart->delete();
        ApiCache::invalidateTags(self::TAGS);
        return response()->json([], 204);
    }

    /**
     * Bulk create spare parts.
     * POST /api/spare_parts/bulk/create
     * 
     * Request body:
     * {
     *   "spare_parts": [
     *     { "name": "...", "sku": "...", ...fields... },
     *     ...
     *   ]
     * }
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'spare_parts' => 'required|array|min:1',
            'spare_parts.*' => 'array',
        ]);

        $created = [];
        foreach ($validated['spare_parts'] as $partData) {
            $req = SparePartRequest::create($partData);
            $sparePart = SparePart::create($req->validated());
            $created[] = $sparePart;
        }

        ApiCache::invalidateTags(self::TAGS);

        return response()->json($created, 201);
    }

    /**
     * Bulk update spare parts.
     * PATCH /api/spare_parts/bulk/update
     * 
     * Request body:
     * {
     *   "updates": [
     *     { "id": 1, "name": "new name", ...fields... },
     *     ...
     *   ]
     * }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:spare_parts,id',
        ]);

        $updated = [];
        foreach ($validated['updates'] as $update) {
            $id = $update['id'];
            unset($update['id']);

            $sparePart = SparePart::findOrFail($id);
            $sparePart->update($update);
            $updated[] = $sparePart;
        }

        ApiCache::invalidateTags(self::TAGS);

        return response()->json($updated);
    }

    /**
     * Bulk delete spare parts.
     * DELETE /api/spare_parts/bulk/delete
     * 
     * Request body:
     * {
     *   "ids": [1, 2, 3, ...],
     * }
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:spare_parts,id',
        ]);

        SparePart::whereIn('id', $validated['ids'])->delete();
        ApiCache::invalidateTags(self::TAGS);

        return response()->json([], 204);
    }

    /**
     * Update stock quantity for a spare part.
     * PATCH /api/spare_parts/{spare_part}/stock
     * 
     * Request body:
     * {
     *   "quantity": 50,    // absolute quantity
     *   "change": 5        // or increment/decrement
     * }
     */
    public function updateStock(Request $request, SparePart $sparePart): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:0',
            'change' => 'nullable|integer',
        ]);

        if (isset($validated['quantity'])) {
            $sparePart->update(['stock_quantity' => $validated['quantity']]);
        } elseif (isset($validated['change'])) {
            $sparePart->increment('stock_quantity', $validated['change']);
            $sparePart->refresh();
        }

        ApiCache::invalidateTags(self::TAGS);

        return response()->json([
            'id' => $sparePart->id,
            'name' => $sparePart->name,
            'stock_quantity' => $sparePart->stock_quantity,
            'low_stock_alarm' => $sparePart->low_stock_alarm,
            'is_low_stock' => $sparePart->stock_quantity <= $sparePart->low_stock_alarm,
        ]);
    }

    /**
     * Get spare parts with low stock.
     * GET /api/spare_parts/low-stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $cacheKey = ApiCache::listKey('spare_parts', $request, 'low_stock');
        $cacheHit = ApiCache::has($cacheKey, self::TAGS);

        $spareParts = ApiCache::remember($cacheKey, self::LIST_TTL_SECONDS, self::TAGS, fn () => SparePart::lowStock()
            ->with(['category', 'brand'])
            ->paginate((int) $request->query('per_page', 20)));

        return response()
            ->json($spareParts)
            ->header('X-Cache-Hit', $cacheHit ? 'true' : 'false');
    }
}
