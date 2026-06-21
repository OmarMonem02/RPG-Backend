<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkInventoryEditRequest;
use App\Http\Requests\MaintenancePartRequest;
use App\Models\MaintenancePart;
use App\Services\CatalogListFilterService;
use App\Services\InventoryBulkEditService;
use App\Services\InventoryImageService;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenancePartController extends Controller
{
    private const TAGS = ['maintenance_parts'];

    public function __construct(
        private readonly InventoryBulkEditService $bulkEditService,
        private readonly InventoryImageService $inventoryImageService,
        private readonly CatalogListFilterService $catalogListFilterService,
    ) {}

    /**
     * Get all spare parts with filtering and search.
     * GET /api/maintenance_parts
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
        $maintenanceParts = $this->buildMaintenancePartsQuery($request)
            ->with(['category', 'brand', 'bikeBlueprints', 'images'])
            ->paginate((int) $request->query('per_page', 20));

        return response()
            ->json($maintenanceParts)
            ->header('X-Cache-Hit', 'false');
    }

    /**
     * Create a new spare part and optionally assign to bike blueprints.
     * POST /api/maintenance_parts
     */
    public function store(MaintenancePartRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $bikeBlueprintIds = $validated['bike_blueprint_ids'] ?? [];
        unset($validated['bike_blueprint_ids']);

        $images = null;
        if (array_key_exists('images', $validated)) {
            $images = $validated['images'];
            unset($validated['images']);
        }

        $maintenancePart = MaintenancePart::create($validated);

        if ($images !== null) {
            $this->inventoryImageService->syncImages($maintenancePart, $images);
        }

        if (!empty($bikeBlueprintIds)) {
            $maintenancePart->bikeBlueprints()->attach($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if (!empty($bikeBlueprintIds)) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($maintenancePart->load(['category', 'brand', 'bikeBlueprints', 'images']), 201);
    }

    /**
     * Get a single spare part.
     * GET /api/maintenance_parts/{maintenance_part}
     */
    public function show(MaintenancePart $maintenancePart): JsonResponse
    {
        return response()
            ->json($maintenancePart->load(['category', 'brand', 'bikeBlueprints', 'images']))
            ->header('X-Cache-Hit', 'false');
    }

    /**
     * Update a spare part.
     * PUT /api/maintenance_parts/{maintenance_part}
     * PATCH /api/maintenance_parts/{maintenance_part}
     */
    public function update(MaintenancePartRequest $request, MaintenancePart $maintenancePart): JsonResponse
    {
        $validated = $request->validated();
        $bikeBlueprintIds = $validated['bike_blueprint_ids'] ?? null;
        unset($validated['bike_blueprint_ids']);

        $images = null;
        if (array_key_exists('images', $validated)) {
            $images = $validated['images'];
            unset($validated['images']);
        }

        $maintenancePart->update($validated);

        if ($images !== null) {
            $this->inventoryImageService->syncImages($maintenancePart, $images);
        }

        if ($bikeBlueprintIds !== null) {
            $maintenancePart->bikeBlueprints()->sync($bikeBlueprintIds);
        }

        $tags = self::TAGS;
        if ($bikeBlueprintIds !== null) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($maintenancePart->load(['category', 'brand', 'bikeBlueprints', 'images']));
    }

    /**
     * Delete a spare part.
     * DELETE /api/maintenance_parts/{maintenance_part}
     */
    public function destroy(MaintenancePart $maintenancePart): JsonResponse
    {
        $maintenancePart->delete();
        ApiCache::invalidateTags(self::TAGS);
        return response()->json([], 204);
    }

    /**
     * Bulk create spare parts.
     * POST /api/maintenance_parts/bulk/create
     * 
     * Request body:
     * {
     *   "maintenance_parts": [
     *     { "name": "...", "sku": "...", ...fields... },
     *     ...
     *   ]
     * }
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'maintenance_parts' => 'required|array|min:1',
            'maintenance_parts.*' => 'array',
        ]);

        $created = [];
        foreach ($validated['maintenance_parts'] as $partData) {
            $req = MaintenancePartRequest::create($partData);
            $maintenancePart = MaintenancePart::create($req->validated());
            $created[] = $maintenancePart;
        }

        ApiCache::invalidateTags(self::TAGS);

        return response()->json($created, 201);
    }

    /**
     * Preview bulk inventory edits for spare parts.
     * POST /api/maintenance_parts/bulk/preview
     */
    public function bulkPreview(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveBulkIds($request);

        return response()->json(
            $this->bulkEditService->preview(MaintenancePart::class, $ids, $request->normalizedChanges())
        );
    }

    /**
     * Apply bulk inventory edits for spare parts.
     * PATCH /api/maintenance_parts/bulk/apply
     */
    public function bulkApply(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveBulkIds($request);
        $changes = $request->normalizedChanges();
        $result = $this->bulkEditService->apply(MaintenancePart::class, $ids, $changes);
        $tags = self::TAGS;
        if ($request->touchesCompatibility()) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($result);
    }

    /**
     * Bulk update spare parts.
     * PATCH /api/maintenance_parts/bulk/update
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
            'updates.*.id' => 'required|integer|exists:maintenance_parts,id',
        ]);

        $updated = $this->bulkEditService->applyLegacyUpdates(MaintenancePart::class, $validated['updates']);
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
            MaintenancePart::class,
            $validated['ids'] ?? null,
            $validated['filters'] ?? null,
        );
    }

    /**
     * Bulk delete spare parts.
     * DELETE /api/maintenance_parts/bulk/delete
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
            'ids.*' => 'integer|exists:maintenance_parts,id',
        ]);

        MaintenancePart::whereIn('id', $validated['ids'])->delete();
        ApiCache::invalidateTags(self::TAGS);

        return response()->json([], 204);
    }

    /**
     * Update stock quantity for a spare part.
     * PATCH /api/maintenance_parts/{maintenance_part}/stock
     * 
     * Request body:
     * {
     *   "quantity": 50,    // absolute quantity
     *   "change": 5        // or increment/decrement
     * }
     */
    public function updateStock(Request $request, MaintenancePart $maintenancePart): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => 'nullable|integer|min:0',
            'change' => 'nullable|integer',
        ]);

        if (isset($validated['quantity'])) {
            $maintenancePart->update(['stock_quantity' => $validated['quantity']]);
        } elseif (isset($validated['change'])) {
            $maintenancePart->increment('stock_quantity', $validated['change']);
            $maintenancePart->refresh();
        }

        ApiCache::invalidateTags(self::TAGS);

        return response()->json([
            'id' => $maintenancePart->id,
            'name' => $maintenancePart->name,
            'stock_quantity' => $maintenancePart->stock_quantity,
            'low_stock_alarm' => $maintenancePart->low_stock_alarm,
            'is_low_stock' => $maintenancePart->stock_quantity <= $maintenancePart->low_stock_alarm,
        ]);
    }

    /**
     * Get spare parts with low stock.
     * GET /api/maintenance_parts/low-stock
     */
    public function lowStock(Request $request): JsonResponse
    {
        $maintenanceParts = MaintenancePart::lowStock()
            ->with(['category', 'brand'])
            ->paginate((int) $request->query('per_page', 20));

        return response()
            ->json($maintenanceParts)
            ->header('X-Cache-Hit', 'false');
    }

    private function buildMaintenancePartsQuery(Request $request)
    {
        $query = MaintenancePart::query();
        $filters = $request->query();
        $query = $this->catalogListFilterService->apply($query, is_array($filters) ? $filters : [], MaintenancePart::class);

        return $query;
    }
}
