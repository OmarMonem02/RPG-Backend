<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkInventoryEditRequest;
use App\Http\Requests\SparePartRequest;
use App\Models\SparePart;
use App\Services\InventoryBulkEditService;
use App\Services\InventoryImageService;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SparePartController extends Controller
{
    private const TAGS = ['spare_parts'];

    public function __construct(
        private readonly InventoryBulkEditService $bulkEditService,
        private readonly InventoryImageService $inventoryImageService,
    ) {}

    /**
     * Get all spare parts with filtering and search.
     * GET /api/spare_parts
     *
     * Query parameters:
     * - search: Search by name, SKU, or part_number
     * - brand_id: Filter by brand
     * - category_id: Filter by category
     * - bike_brand_id: Filter by compatible bike brand
     * - bike_model: Filter by compatible bike model
     * - bike_year: Filter by compatible bike year
     * - bike_year_from: Filter by compatible bike year from
     * - bike_year_to: Filter by compatible bike year to
     * - low_stock: Show only low stock items (true/false)
     * - tags: Comma-separated tag filters (partial match, AND logic)
     * - per_page: Items per page (default: 20)
     */
    public function index(Request $request): JsonResponse
    {
        $spareParts = $this->buildSparePartsQuery($request)
            ->with(['category', 'brand', 'bikeBlueprints', 'images'])
            ->paginate((int) $request->query('per_page', 20));

        return response()
            ->json($spareParts)
            ->header('X-Cache-Hit', 'false');
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

        $images = null;
        if (array_key_exists('images', $validated)) {
            $images = $validated['images'];
            unset($validated['images']);
        }

        $sparePart = SparePart::create($validated);

        if ($images !== null) {
            $this->inventoryImageService->syncImages($sparePart, $images);
        }

        if (!empty($bikeBlueprintIds)) {
            $sparePart->bikeBlueprints()->attach($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if (!empty($bikeBlueprintIds)) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($sparePart->load(['category', 'brand', 'bikeBlueprints', 'images']), 201);
    }

    /**
     * Get a single spare part.
     * GET /api/spare_parts/{spare_part}
     */
    public function show(SparePart $sparePart): JsonResponse
    {
        return response()
            ->json($sparePart->load(['category', 'brand', 'bikeBlueprints', 'images']))
            ->header('X-Cache-Hit', 'false');
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

        $images = null;
        if (array_key_exists('images', $validated)) {
            $images = $validated['images'];
            unset($validated['images']);
        }

        $sparePart->update($validated);

        if ($images !== null) {
            $this->inventoryImageService->syncImages($sparePart, $images);
        }

        if ($bikeBlueprintIds !== null) {
            $sparePart->bikeBlueprints()->sync($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if ($bikeBlueprintIds !== null) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($sparePart->load(['category', 'brand', 'bikeBlueprints', 'images']));
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
     * Preview bulk inventory edits for spare parts.
     * POST /api/spare_parts/bulk/preview
     */
    public function bulkPreview(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveBulkIds($request);

        return response()->json(
            $this->bulkEditService->preview(SparePart::class, $ids, $request->normalizedChanges())
        );
    }

    /**
     * Apply bulk inventory edits for spare parts.
     * PATCH /api/spare_parts/bulk/apply
     */
    public function bulkApply(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveBulkIds($request);
        $result = $this->bulkEditService->apply(SparePart::class, $ids, $request->normalizedChanges());
        ApiCache::invalidateTags(self::TAGS);

        return response()->json($result);
    }

    /**
     * Bulk update spare parts.
     * PATCH /api/spare_parts/bulk/update
     *
     * New shape: { "changes": { ... }, "ids": [...], "filters": {...} }
     * Legacy: { "updates": [ { "id": 1, ...fields }, ... ] }
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        if ($request->has('changes')) {
            $bulkRequest = BulkInventoryEditRequest::createFrom($request);
            $bulkRequest->setContainer(app())->setRedirector(app('redirect'));
            $bulkRequest->validateResolved();

            return $this->bulkApply($bulkRequest);
        }

        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:spare_parts,id',
        ]);

        $updated = $this->bulkEditService->applyLegacyUpdates(SparePart::class, $validated['updates']);
        ApiCache::invalidateTags(self::TAGS);

        return response()->json($updated);
    }

    /**
     * @return array<int>
     */
    private function resolveBulkIds(BulkInventoryEditRequest $request): array
    {
        $validated = $request->validated();

        return $this->bulkEditService->resolveIds(
            SparePart::class,
            $validated['ids'] ?? null,
            $validated['filters'] ?? null,
        );
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
        $spareParts = SparePart::lowStock()
            ->with(['category', 'brand'])
            ->paginate((int) $request->query('per_page', 20));

        return response()
            ->json($spareParts)
            ->header('X-Cache-Hit', 'false');
    }

    private function buildSparePartsQuery(Request $request)
    {
        $tags = SparePart::parseTagsQueryParam($request->query('tags'));

        $query = SparePart::query()
            ->search($request->query('search'))
            ->byTags($tags)
            ->byBrand($request->query('brand_id'))
            ->byCategory($request->query('category_id'))
            ->byCurrency($request->query('currency') ? strtoupper((string) $request->query('currency')) : null)
            ->byBikeBrand($request->query('bike_brand_id'))
            ->byBikeModel($request->query('bike_model'))
            ->byBikeYear($request->query('bike_year'));

        if ($request->query('bike_year_from') || $request->query('bike_year_to')) {
            $query->byBikeYearRange(
                $request->query('bike_year_from') ? (int) $request->query('bike_year_from') : null,
                $request->query('bike_year_to') ? (int) $request->query('bike_year_to') : null
            );
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        return $query;
    }
}
