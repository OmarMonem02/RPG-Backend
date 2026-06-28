<?php

namespace App\Services;

use App\Models\CustomerSale;
use App\Models\History;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\ItemDiscountResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly SaleInventoryService $inventory,
        private readonly SalePresenterService $presenter,
        private readonly SaleQueryService $queryService,
        private readonly SaleCatalogService $catalogService,
    ) {
    }

    public function paginateSales(array $filters): LengthAwarePaginator
    {
        return $this->queryService->paginate($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportSalesQuery(array $filters): Builder
    {
        return $this->queryService->exportQuery($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportUnstoredSaleItemsQuery(array $filters): Builder
    {
        return $this->queryService->exportUnstoredItemsQuery($filters);
    }

    public function getSaleDetails(Sale $sale): array
    {
        return $this->presenter->serializeSale($this->presenter->loadSaleRelations($sale));
    }

    public function refreshSaleTotals(Sale $sale): Sale
    {
        return $this->recalculateSale($sale);
    }

    public function paginateAdjustments(Sale $sale, int $perPage = 20): LengthAwarePaginator
    {
        return $sale->adjustments()
            ->with('user')
            ->paginate($perPage)
            ->through(fn (SaleAdjustment $adjustment) => $this->presenter->serializeAdjustment($adjustment));
    }

    public function paginateSaleHistory(Sale $sale, int $perPage = 50): LengthAwarePaginator
    {
        $itemIds = SaleItem::query()
            ->withTrashed()
            ->where('sale_id', $sale->id)
            ->pluck('id');

        return History::query()
            ->with('user')
            ->where(function (Builder $query) use ($sale, $itemIds): void {
                $query->where(function (Builder $saleQuery) use ($sale): void {
                    $saleQuery
                        ->where('model_type', Sale::class)
                        ->where('model_id', $sale->id);
                });

                if ($itemIds->isNotEmpty()) {
                    $query->orWhere(function (Builder $itemQuery) use ($itemIds): void {
                        $itemQuery
                            ->where('model_type', SaleItem::class)
                            ->whereIn('model_id', $itemIds);
                    });
                }
            })
            ->latest()
            ->paginate($perPage);
    }

    public function catalog(array $filters): LengthAwarePaginator
    {
        return $this->catalogService->catalog($filters);
    }

    public function create(array $data, int $userId): array
    {
        $sale = DB::transaction(function () use ($data, $userId) {
            $discountApprovalRequestId = isset($data['discount_approval_request_id'])
                ? (int) $data['discount_approval_request_id']
                : null;

            $sale = Sale::create([
                'customer_id' => $data['customer_id'],
                'customer_address_id' => $data['customer_address_id'] ?? null,
                'user_id' => $userId,
                'seller_id' => $data['seller_id'] ?? null,
                'total' => 0,
                'discount' => (float) ($data['discount'] ?? 0),
                'payment_method_id' => $data['payment_method_id'],
                'type' => $data['type'],
                'status' => Sale::defaultStatusForType($data['type']),
                'delivery_status' => $data['delivery_status'] ?? null,
                'shipping_fee' => (float) ($data['shipping_fee'] ?? 0),
                'is_maintenance' => (bool) ($data['is_maintenance'] ?? false),
            ]);

            $user = User::query()->find($userId);

            foreach ($data['items'] as $itemData) {
                $unitDiscount = ItemDiscountResolver::resolveUnitDiscount(
                    $user,
                    (float) ($itemData['discount'] ?? 0),
                    (float) $itemData['selling_price'],
                    ! empty($itemData['product_id']) ? (int) $itemData['product_id'] : null,
                    ! empty($itemData['spare_part_id']) ? (int) $itemData['spare_part_id'] : null,
                    ! empty($itemData['maintenance_service_id']) ? (int) $itemData['maintenance_service_id'] : null,
                    ! empty($itemData['bike_for_sale_id']) ? (int) $itemData['bike_for_sale_id'] : null,
                    ! empty($itemData['maintenance_part_id']) ? (int) $itemData['maintenance_part_id'] : null,
                    isset($itemData['discount_approval_request_id'])
                        ? (int) $itemData['discount_approval_request_id']
                        : null,
                );

                $itemData['discount'] = $unitDiscount;
                $this->inventory->createSaleItem($sale, $itemData);

                $itemApprovalRequestId = isset($itemData['discount_approval_request_id'])
                    ? (int) $itemData['discount_approval_request_id']
                    : null;

                if ($itemApprovalRequestId && $unitDiscount > 0) {
                    ItemDiscountResolver::consumeItemApproval(
                        $itemApprovalRequestId,
                        $userId,
                        $unitDiscount,
                        consumedSaleId: (int) $sale->id,
                    );
                }
            }

            CustomerSale::updateOrCreate(
                ['customer_id' => $data['customer_id'], 'sale_id' => $sale->id],
                ['customer_id' => $data['customer_id'], 'sale_id' => $sale->id],
            );

            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'created',
                summary: $this->formatUserSummary($userId, "created sale #{$sale->id}"),
                before: null,
                after: $this->presenter->serializeSaleForAudit($sale->load('items')),
                amountDelta: (float) $sale->total,
                meta: ['items_count' => $sale->items->count()],
            );

            if ($discountApprovalRequestId && (float) ($data['discount'] ?? 0) > 0) {
                app(ApprovalRequestService::class)->consumeApprovedRequest(
                    $discountApprovalRequestId,
                    $userId,
                    (float) ($data['discount'] ?? 0),
                    consumedSaleId: (int) $sale->id,
                );
            }

            return $sale;
        });

        return $this->presenter->serializeSale($sale);
    }

    public function updateSale(Sale $sale, array $data, int $userId): array
    {
        DB::transaction(function () use ($sale, $data, $userId) {
            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;

            $sale->fill($data);
            $sale->save();
            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'sale_updated',
                summary: $this->formatUserSummary($userId, "updated sale #{$sale->id} details"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                meta: ['updated_fields' => array_keys($data)],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function addItem(Sale $sale, array $itemData, int $userId): array
    {
        DB::transaction(function () use ($sale, $itemData, $userId) {
            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;

            $saleItem = $this->inventory->createSaleItem($sale, $itemData);
            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'item_added',
                summary: $this->formatUserSummary($userId, 'added item ' . $this->inventory->describeSaleItem($saleItem) . " to sale #{$sale->id}"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                meta: ['sale_item_id' => $saleItem->id],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function updateItem(Sale $sale, SaleItem $saleItem, array $data, int $userId): array
    {
        DB::transaction(function () use ($sale, $saleItem, $data, $userId) {
            $saleItem = $this->guardSaleItemBelongsToSale($sale, $saleItem);

            if ($saleItem->returned_qty > 0 || $saleItem->status !== SaleItem::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'sale_item_id' => ['Only fully active sale items can be edited directly. Use return or exchange endpoints for adjusted items.'],
                ]);
            }

            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;
            $originalQty = (int) $saleItem->qty;

            $saleItem->fill($data);
            $newQty = (int) $saleItem->qty;
            $this->inventory->assertQuantityRulesForExistingItem($saleItem, $newQty);
            $this->inventory->syncInventoryForQtyChange($saleItem, $newQty - $originalQty);
            $saleItem->save();

            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'item_updated',
                summary: $this->formatUserSummary($userId, 'updated item ' . $this->inventory->describeSaleItem($saleItem) . " in sale #{$sale->id}"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                meta: ['sale_item_id' => $saleItem->id, 'updated_fields' => array_keys($data)],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function removeItem(Sale $sale, SaleItem $saleItem, int $userId): array
    {
        DB::transaction(function () use ($sale, $saleItem, $userId) {
            $saleItem = $this->guardSaleItemBelongsToSale($sale, $saleItem);

            if ($saleItem->returned_qty > 0) {
                throw ValidationException::withMessages([
                    'sale_item_id' => ['Partially returned or exchanged items cannot be deleted directly.'],
                ]);
            }

            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;

            $this->inventory->restoreInventoryForSaleItem($saleItem, $saleItem->qty);
            $saleItem->delete();
            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'item_removed',
                summary: $this->formatUserSummary($userId, 'removed item ' . $this->inventory->describeSaleItem($saleItem) . " from sale #{$sale->id}"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                meta: ['sale_item_id' => $saleItem->id],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function processReturn(Sale $sale, array $data, int $userId): array
    {
        DB::transaction(function () use ($sale, $data, $userId) {
            $saleItem = $this->guardSaleItemBelongsToSale($sale, SaleItem::query()->findOrFail($data['sale_item_id']));
            $returnQty = (int) $data['qty'];

            $this->inventory->assertReturnableQuantity($saleItem, $returnQty);

            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;
            $refundAmount = $this->inventory->lineSubtotal($saleItem, $returnQty);

            $this->inventory->restoreInventoryForSaleItem($saleItem, $returnQty);
            $saleItem->returned_qty += $returnQty;
            $saleItem->status = $saleItem->remainingQty() === 0
                ? SaleItem::STATUS_RETURNED
                : SaleItem::STATUS_PARTIALLY_RETURNED;
            $saleItem->save();

            $sale = $this->recalculateSale($sale);

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'item_returned',
                summary: $this->formatUserSummary($userId, 'returned ' . $returnQty . ' x ' . $this->inventory->describeSaleItem($saleItem) . " from sale #{$sale->id}"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                refundAmount: $refundAmount,
                notes: $data['notes'] ?? null,
                meta: ['sale_item_id' => $saleItem->id, 'returned_qty' => $returnQty],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function processExchange(Sale $sale, array $data, int $userId): array
    {
        DB::transaction(function () use ($sale, $data, $userId) {
            $saleItem = $this->guardSaleItemBelongsToSale($sale, SaleItem::query()->findOrFail($data['sale_item_id']));
            $exchangeQty = (int) $data['qty'];

            $this->inventory->assertReturnableQuantity($saleItem, $exchangeQty);

            $before = $this->presenter->serializeSaleForAudit($sale->load('items'));
            $beforeTotal = (float) $sale->total;

            $this->inventory->restoreInventoryForSaleItem($saleItem, $exchangeQty);
            $saleItem->returned_qty += $exchangeQty;
            $saleItem->status = $saleItem->remainingQty() === 0
                ? SaleItem::STATUS_EXCHANGED
                : SaleItem::STATUS_PARTIALLY_RETURNED;
            $saleItem->save();

            $newSubtotal = 0;
            $replacementsCount = 0;
            foreach ($data['replacements'] as $itemData) {
                $replacement = $this->inventory->createSaleItem($sale, $itemData, $saleItem->id);
                $newSubtotal += $this->inventory->lineSubtotal($replacement, (int) $itemData['qty']);
                $replacementsCount++;
            }

            $sale = $this->recalculateSale($sale);

            $oldSubtotal = $this->inventory->lineSubtotal($saleItem, $exchangeQty);
            $difference = $newSubtotal - $oldSubtotal;

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'item_exchanged',
                summary: $this->formatUserSummary($userId, "exchanged {$exchangeQty} x " . $this->inventory->describeSaleItem($saleItem) . " in sale #{$sale->id}"),
                before: $before,
                after: $this->presenter->serializeSaleForAudit($sale),
                amountDelta: (float) $sale->total - $beforeTotal,
                refundAmount: $difference < 0 ? abs($difference) : 0,
                extraAmountDue: $difference > 0 ? $difference : 0,
                notes: $data['notes'] ?? null,
                meta: [
                    'sale_item_id' => $saleItem->id,
                    'exchange_qty' => $exchangeQty,
                    'replacements_added' => $replacementsCount,
                ],
            );
        });

        return $this->presenter->serializeSale($sale);
    }

    public function delete(Sale $sale, int $userId): void
    {
        DB::transaction(function () use ($sale, $userId) {
            $sale = $sale->loadMissing('items');
            $before = $this->presenter->serializeSaleForAudit($sale);
            $beforeTotal = (float) $sale->total;
            $restoredItems = 0;

            foreach ($sale->items as $item) {
                $remainingQty = $item->remainingQty();
                if ($remainingQty > 0) {
                    $this->inventory->restoreInventoryForSaleItem($item, $remainingQty);
                    $restoredItems++;
                }

                $item->delete();
            }

            $this->logAdjustment(
                sale: $sale,
                userId: $userId,
                actionType: 'sale_deleted',
                summary: $this->formatUserSummary($userId, "deleted sale #{$sale->id}"),
                before: $before,
                after: null,
                amountDelta: -$beforeTotal,
                meta: ['restored_items_count' => $restoredItems],
            );

            $sale->delete();
        });
    }

    private function recalculateSale(Sale $sale): Sale
    {
        $sale->unsetRelation('items');
        $sale->load('items');

        $subtotal = $sale->items
            ->filter(fn (SaleItem $item) => ! $item->trashed())
            ->sum(fn (SaleItem $item) => $this->inventory->lineSubtotal($item, $item->remainingQty()));

        $sale->forceFill([
            'total' => max(0, $subtotal + (float) $sale->shipping_fee - (float) $sale->discount),
        ])->save();

        return $sale;
    }

    private function guardSaleItemBelongsToSale(Sale $sale, SaleItem $saleItem): SaleItem
    {
        if ($saleItem->sale_id !== $sale->id) {
            throw (new ModelNotFoundException())->setModel(SaleItem::class, [$saleItem->id]);
        }

        return $saleItem->loadMissing('product', 'sparePart', 'maintenanceService', 'bikeForSale.bikeBlueprint.brand');
    }

    private function logAdjustment(
        Sale $sale,
        int $userId,
        string $actionType,
        string $summary,
        ?array $before,
        ?array $after,
        float $amountDelta = 0,
        float $refundAmount = 0,
        float $extraAmountDue = 0,
        ?string $notes = null,
        ?array $meta = null,
    ): void {
        $sale->adjustments()->create([
            'user_id' => $userId,
            'action_type' => $actionType,
            'summary' => $summary,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'amount_delta' => $amountDelta,
            'refund_amount' => $refundAmount,
            'extra_amount_due' => $extraAmountDue,
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }

    private function formatUserSummary(int $userId, string $action): string
    {
        $userName = User::query()->find($userId)?->name ?? 'Unknown User';

        return "User {$userName} {$action}";
    }
}
