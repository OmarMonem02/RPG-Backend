<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkInventoryEditRequest;
use App\Models\Product;
use App\Services\InventoryBulkEditService;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class ProductBulkController extends Controller
{
    private const TAGS = ['products'];

    public function __construct(
        private readonly InventoryBulkEditService $bulkEditService,
    ) {}

    /**
     * POST /api/products/bulk/preview
     */
    public function preview(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveIds($request);

        return response()->json(
            $this->bulkEditService->preview(Product::class, $ids, $request->normalizedChanges())
        );
    }

    /**
     * PATCH /api/products/bulk/apply
     */
    public function apply(BulkInventoryEditRequest $request): JsonResponse
    {
        $ids = $this->resolveIds($request);
        $changes = $request->normalizedChanges();

        $result = $this->bulkEditService->apply(Product::class, $ids, $changes);
        $tags = self::TAGS;
        if ($request->touchesCompatibility()) {
            $tags[] = 'blueprints';
        }
        ApiCache::invalidateTags($tags);

        return response()->json($result);
    }

    /**
     * @return array<int>
     */
    private function resolveIds(BulkInventoryEditRequest $request): array
    {
        $validated = $request->validated();

        return $this->bulkEditService->resolveIds(
            Product::class,
            $validated['ids'] ?? null,
            $validated['filters'] ?? null,
        );
    }
}
