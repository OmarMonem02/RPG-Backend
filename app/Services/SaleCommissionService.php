<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaleCommissionService
{
    public function lineSubtotalSql(): string
    {
        $remainingQty = 'CASE WHEN (sale_items.qty - sale_items.returned_qty) > 0 THEN (sale_items.qty - sale_items.returned_qty) ELSE 0 END';
        $rawSubtotal = "((sale_items.selling_price - sale_items.discount) * {$remainingQty})";

        return "CASE WHEN sale_items.id IS NULL THEN 0 WHEN {$rawSubtotal} > 0 THEN {$rawSubtotal} ELSE 0 END";
    }

    public function eligibleLineSubtotalSql(): string
    {
        $lineSubtotal = $this->lineSubtotalSql();

        return "CASE
            WHEN sale_items.product_id IS NOT NULL AND COALESCE(products.have_commission, 0) = 1 THEN {$lineSubtotal}
            WHEN sale_items.spare_part_id IS NOT NULL AND COALESCE(spare_parts.have_commission, 0) = 1 THEN {$lineSubtotal}
            WHEN sale_items.maintenance_part_id IS NOT NULL AND COALESCE(maintenance_parts.have_commission, 0) = 1 THEN {$lineSubtotal}
            WHEN sale_items.bike_for_sale_id IS NOT NULL AND COALESCE(bike_for_sale.have_commission, 0) = 1 THEN {$lineSubtotal}
            WHEN sale_items.maintenance_service_id IS NOT NULL AND COALESCE(maintenance_services.have_commission, 0) = 1 THEN {$lineSubtotal}
            ELSE 0
        END";
    }

    public function lineCommissionAmountSql(): string
    {
        $lineSubtotal = $this->lineSubtotalSql();

        return "CASE
            WHEN sale_items.product_id IS NOT NULL AND COALESCE(products.have_commission, 0) = 1
                THEN {$lineSubtotal} * COALESCE(sellers.products_commission_rate, 0) / 100
            WHEN sale_items.spare_part_id IS NOT NULL AND COALESCE(spare_parts.have_commission, 0) = 1
                THEN {$lineSubtotal} * COALESCE(sellers.spare_parts_commission_rate, 0) / 100
            WHEN sale_items.maintenance_part_id IS NOT NULL AND COALESCE(maintenance_parts.have_commission, 0) = 1
                THEN {$lineSubtotal} * COALESCE(sellers.maintenance_parts_commission_rate, 0) / 100
            WHEN sale_items.bike_for_sale_id IS NOT NULL AND COALESCE(bike_for_sale.have_commission, 0) = 1
                THEN {$lineSubtotal} * COALESCE(sellers.bikes_for_sale_commission_rate, 0) / 100
            WHEN sale_items.maintenance_service_id IS NOT NULL AND COALESCE(maintenance_services.have_commission, 0) = 1
                THEN {$lineSubtotal} * COALESCE(sellers.maintenance_services_commission_rate, 0) / 100
            ELSE 0
        END";
    }

    public function lineCommissionBase(SaleItem $item): float
    {
        if (! $this->itemHasCommission($item)) {
            return 0.0;
        }

        return $this->rawLineSubtotal($item);
    }

    public function lineCommissionAmount(SaleItem $item, ?Seller $seller): float
    {
        if (! $seller || ! $this->itemHasCommission($item)) {
            return 0.0;
        }

        $base = $this->rawLineSubtotal($item);
        $rate = $this->resolveCommissionRate($item, $seller);

        return round($base * ($rate / 100), 2);
    }

    /**
     * @return array{
     *     commission_base: float,
     *     commission_amount: float,
     *     lines: array<int, array{sale_item_id: int, commission_base: float, commission_amount: float}>
     * }
     */
    public function saleCommissionTotals(Sale $sale): array
    {
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            return [
                'commission_base' => 0.0,
                'commission_amount' => 0.0,
                'lines' => [],
            ];
        }

        $seller = $sale->relationLoaded('seller') ? $sale->seller : $sale->seller()->first();
        $commissionBase = 0.0;
        $commissionAmount = 0.0;
        $lines = [];

        foreach ($sale->items as $item) {
            $item->loadMissing(
                'product',
                'sparePart',
                'maintenancePart',
                'bikeForSale',
                'maintenanceService',
            );

            $lineBase = $this->lineCommissionBase($item);
            $lineAmount = $this->lineCommissionAmount($item, $seller);

            $commissionBase += $lineBase;
            $commissionAmount += $lineAmount;

            $lines[] = [
                'sale_item_id' => $item->id,
                'commission_base' => round($lineBase, 2),
                'commission_amount' => $lineAmount,
            ];
        }

        return [
            'commission_base' => round($commissionBase, 2),
            'commission_amount' => round($commissionAmount, 2),
            'lines' => $lines,
        ];
    }

    public function sellerHasCommission(Seller $seller): bool
    {
        return $this->sellerMaxRate($seller) > 0;
    }

    public function sellerHasHighCommission(Seller $seller): bool
    {
        return collect($this->sellerRates($seller))->contains(fn (float $rate) => $rate >= 10);
    }

    /**
     * @return array<int, float>
     */
    public function sellerRates(Seller $seller): array
    {
        return [
            (float) $seller->products_commission_rate,
            (float) $seller->spare_parts_commission_rate,
            (float) $seller->maintenance_parts_commission_rate,
            (float) $seller->bikes_for_sale_commission_rate,
            (float) $seller->maintenance_services_commission_rate,
        ];
    }

    public function sellerAverageRate(Seller $seller): float
    {
        $rates = $this->sellerRates($seller);

        return array_sum($rates) / count($rates);
    }

    public function sellerMaxRate(Seller $seller): float
    {
        return max($this->sellerRates($seller));
    }

    public function maxRateOrderExpression(): string
    {
        $a = 'COALESCE(sellers.products_commission_rate, 0)';
        $b = 'COALESCE(sellers.spare_parts_commission_rate, 0)';
        $c = 'COALESCE(sellers.maintenance_parts_commission_rate, 0)';
        $d = 'COALESCE(sellers.bikes_for_sale_commission_rate, 0)';
        $e = 'COALESCE(sellers.maintenance_services_commission_rate, 0)';

        if (DB::connection()->getDriverName() === 'sqlite') {
            return "MAX(MAX(MAX({$a}, {$b}), MAX({$c}, {$d})), {$e})";
        }

        return "GREATEST({$a}, {$b}, {$c}, {$d}, {$e})";
    }

    public function applyCommissionJoins(Builder $query): Builder
    {
        return $query
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('spare_parts', 'sale_items.spare_part_id', '=', 'spare_parts.id')
            ->leftJoin('maintenance_parts', 'sale_items.maintenance_part_id', '=', 'maintenance_parts.id')
            ->leftJoin('bike_for_sale', 'sale_items.bike_for_sale_id', '=', 'bike_for_sale.id')
            ->leftJoin('maintenance_services', 'sale_items.maintenance_service_id', '=', 'maintenance_services.id')
            ->leftJoin('sellers', 'sales.seller_id', '=', 'sellers.id');
    }

    private function rawLineSubtotal(SaleItem $item): float
    {
        $qty = max(0, $item->qty - $item->returned_qty);

        return max(0.0, ((float) $item->selling_price - (float) $item->discount) * $qty);
    }

    private function itemHasCommission(SaleItem $item): bool
    {
        return match (true) {
            ! is_null($item->product_id) => (bool) ($item->product?->have_commission ?? false),
            ! is_null($item->spare_part_id) => (bool) ($item->sparePart?->have_commission ?? false),
            ! is_null($item->maintenance_part_id) => (bool) ($item->maintenancePart?->have_commission ?? false),
            ! is_null($item->bike_for_sale_id) => (bool) ($item->bikeForSale?->have_commission ?? false),
            ! is_null($item->maintenance_service_id) => (bool) ($item->maintenanceService?->have_commission ?? false),
            default => false,
        };
    }

    private function resolveCommissionRate(SaleItem $item, Seller $seller): float
    {
        return match (true) {
            ! is_null($item->product_id) => (float) $seller->products_commission_rate,
            ! is_null($item->spare_part_id) => (float) $seller->spare_parts_commission_rate,
            ! is_null($item->maintenance_part_id) => (float) $seller->maintenance_parts_commission_rate,
            ! is_null($item->bike_for_sale_id) => (float) $seller->bikes_for_sale_commission_rate,
            ! is_null($item->maintenance_service_id) => (float) $seller->maintenance_services_commission_rate,
            default => 0.0,
        };
    }
}
