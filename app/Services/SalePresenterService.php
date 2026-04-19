<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\SaleItem;

class SalePresenterService
{
    /**
     * @var array<int, string>
     */
    public const SALE_RELATIONS = [
        'customer',
        'user',
        'seller',
        'paymentMethod',
        'items.product.brand',
        'items.product.category',
        'items.sparePart.brand',
        'items.sparePart.category',
        'items.sparePart.bikeBlueprints.brand',
        'items.maintenanceService.sector',
        'items.bikeForSale.bikeBlueprint.brand',
        'items.replacedFrom',
        'adjustments.user',
    ];

    public function __construct(private readonly SaleInventoryService $inventory)
    {
    }

    public function loadSaleRelations(Sale $sale): Sale
    {
        return $sale->load(self::SALE_RELATIONS);
    }

    public function serializeSale(Sale $sale): array
    {
        $sale = $this->loadSaleRelations($sale);

        return [
            'id' => $sale->id,
            'customer_id' => $sale->customer_id,
            'user_id' => $sale->user_id,
            'seller_id' => $sale->seller_id,
            'total' => (float) $sale->total,
            'discount' => (float) $sale->discount,
            'payment_method_id' => $sale->payment_method_id,
            'type' => $sale->type,
            'status' => $sale->status,
            'delivery_status' => $sale->delivery_status,
            'shipping_fee' => (float) $sale->shipping_fee,
            'is_maintenance' => (bool) $sale->is_maintenance,
            'created_at' => $sale->created_at,
            'updated_at' => $sale->updated_at,
            'customer' => $sale->customer,
            'user' => $sale->user,
            'seller' => $sale->seller,
            'payment_method' => $sale->paymentMethod,
            'items' => $sale->items
                ->map(fn (SaleItem $item) => $this->serializeSaleItem($item))
                ->values()
                ->all(),
            'adjustments' => $sale->relationLoaded('adjustments')
                ? $sale->adjustments->map(fn (SaleAdjustment $adjustment) => $this->serializeAdjustment($adjustment))->values()->all()
                : [],
        ];
    }

    public function serializeSaleForAudit(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'total' => (float) $sale->total,
            'discount' => (float) $sale->discount,
            'status' => $sale->status,
            'items' => $sale->items
                ->map(fn (SaleItem $item) => $this->serializeSaleItemForAudit($item))
                ->values()
                ->all(),
        ];
    }

    public function serializeSaleItem(SaleItem $item): array
    {
        $item = $item->loadMissing('product.brand', 'product.category', 'sparePart.brand', 'sparePart.category', 'maintenanceService.sector', 'bikeForSale.bikeBlueprint.brand');
        [$type, $resolvedItem] = $this->resolveSellableForSerialization($item);

        return [
            'id' => $item->id,
            'sale_id' => $item->sale_id,
            'product_id' => $item->product_id,
            'spare_part_id' => $item->spare_part_id,
            'maintenance_service_id' => $item->maintenance_service_id,
            'bike_for_sale_id' => $item->bike_for_sale_id,
            'selling_price' => (float) $item->selling_price,
            'discount' => (float) $item->discount,
            'qty' => (int) $item->qty,
            'returned_qty' => (int) $item->returned_qty,
            'remaining_qty' => $item->remainingQty(),
            'status' => $item->status,
            'replaced_from_sale_item_id' => $item->replaced_from_sale_item_id,
            'item_type' => $type,
            'item_label' => $this->inventory->describeSaleItem($item),
            'resolved_item' => $resolvedItem,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    public function serializeSaleItemForAudit(SaleItem $item): array
    {
        return [
            'id' => $item->id,
            'item_label' => $this->inventory->describeSaleItem($item),
            'selling_price' => (float) $item->selling_price,
            'discount' => (float) $item->discount,
            'qty' => (int) $item->qty,
            'returned_qty' => (int) $item->returned_qty,
            'status' => $item->status,
        ];
    }

    public function serializeAdjustment(SaleAdjustment $adjustment): array
    {
        return [
            'id' => $adjustment->id,
            'sale_id' => $adjustment->sale_id,
            'user_id' => $adjustment->user_id,
            'user' => $adjustment->user,
            'action_type' => $adjustment->action_type,
            'summary' => $adjustment->summary,
            'before_snapshot' => $adjustment->before_snapshot,
            'after_snapshot' => $adjustment->after_snapshot,
            'amount_delta' => (float) $adjustment->amount_delta,
            'refund_amount' => (float) $adjustment->refund_amount,
            'extra_amount_due' => (float) $adjustment->extra_amount_due,
            'notes' => $adjustment->notes,
            'meta' => $adjustment->meta,
            'created_at' => $adjustment->created_at,
            'updated_at' => $adjustment->updated_at,
        ];
    }

    /**
     * @return array{0: string|null, 1: array<string, mixed>|null}
     */
    private function resolveSellableForSerialization(SaleItem $item): array
    {
        return match (true) {
            ! is_null($item->product) => ['product', $item->product->toArray()],
            ! is_null($item->sparePart) => ['spare_part', $item->sparePart->toArray()],
            ! is_null($item->maintenanceService) => ['maintenance_service', $item->maintenanceService->toArray()],
            ! is_null($item->bikeForSale) => ['bike', $item->bikeForSale->toArray()],
            default => [null, null],
        };
    }
}
