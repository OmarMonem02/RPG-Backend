<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SaleCommissionService
{
    /** @var array<string, mixed>|null */
    private ?array $commissionSchema = null;

    public function lineSubtotalSql(): string
    {
        $remainingQty = 'CASE WHEN (sale_items.qty - sale_items.returned_qty) > 0 THEN (sale_items.qty - sale_items.returned_qty) ELSE 0 END';
        $rawSubtotal = "((sale_items.selling_price - sale_items.discount) * {$remainingQty})";

        return "CASE WHEN sale_items.id IS NULL THEN 0 WHEN {$rawSubtotal} > 0 THEN {$rawSubtotal} ELSE 0 END";
    }

    public function eligibleLineSubtotalSql(): string
    {
        return $this->buildCaseSql($this->eligibleLineBranches());
    }

    public function lineCommissionAmountSql(): string
    {
        return $this->buildCaseSql($this->commissionAmountBranches());
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
        $schema = $this->commissionSchema();

        if ($schema['seller_per_type_rates']) {
            return [
                (float) $seller->products_commission_rate,
                (float) $seller->spare_parts_commission_rate,
                (float) $seller->maintenance_parts_commission_rate,
                (float) $seller->bikes_for_sale_commission_rate,
                (float) $seller->maintenance_services_commission_rate,
            ];
        }

        if ($schema['seller_legacy_rate']) {
            $rate = (float) $seller->commission_rate;

            return [$rate, $rate, $rate, $rate, $rate];
        }

        return [0.0, 0.0, 0.0, 0.0, 0.0];
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
        $schema = $this->commissionSchema();

        if (! $schema['seller_per_type_rates']) {
            if ($schema['seller_legacy_rate']) {
                return 'COALESCE(sellers.commission_rate, 0)';
            }

            return '0';
        }

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
        $query
            ->leftJoin('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('spare_parts', 'sale_items.spare_part_id', '=', 'spare_parts.id');

        if ($this->commissionSchema()['maintenance_parts']) {
            $query->leftJoin('maintenance_parts', 'sale_items.maintenance_part_id', '=', 'maintenance_parts.id');
        }

        return $query
            ->leftJoin('bike_for_sale', 'sale_items.bike_for_sale_id', '=', 'bike_for_sale.id')
            ->leftJoin('maintenance_services', 'sale_items.maintenance_service_id', '=', 'maintenance_services.id')
            ->leftJoin('sellers', 'sales.seller_id', '=', 'sellers.id');
    }

    /**
     * Map validated seller input to database columns for the current schema.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function sellerAttributesFromInput(array $validated): array
    {
        $attributes = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
        ];

        if ($this->commissionSchema()['seller_per_type_rates']) {
            foreach ($this->sellerRateColumns() as $column) {
                $attributes[$column] = (float) ($validated[$column] ?? 0);
            }

            return $attributes;
        }

        if ($this->commissionSchema()['seller_legacy_rate']) {
            $attributes['commission_rate'] = $this->legacyCommissionRateFromInput($validated);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function legacyCommissionRateFromInput(array $validated): float
    {
        $rates = array_map(
            fn (string $column): float => (float) ($validated[$column] ?? 0),
            $this->sellerRateColumns(),
        );

        return max($rates);
    }

    /**
     * @return array<string, mixed>
     */
    public function sellerSchemaSnapshot(): array
    {
        $schema = $this->commissionSchema();
        $sellerColumns = [];

        foreach ([
            'commission_rate',
            'products_commission_rate',
            'spare_parts_commission_rate',
            'maintenance_parts_commission_rate',
            'bikes_for_sale_commission_rate',
            'maintenance_services_commission_rate',
        ] as $column) {
            $sellerColumns[$column] = \Illuminate\Support\Facades\Schema::hasColumn('sellers', $column);
        }

        return [
            'schema' => $schema,
            'seller_columns' => $sellerColumns,
            'seller_count' => (int) \App\Models\Seller::query()->count(),
            'completed_sales_count' => (int) \App\Models\Sale::query()->where('status', \App\Models\Sale::STATUS_COMPLETED)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commissionSchema(): array
    {
        if ($this->commissionSchema !== null) {
            return $this->commissionSchema;
        }

        return $this->commissionSchema = [
            'maintenance_parts' => Schema::hasTable('maintenance_parts')
                && Schema::hasColumn('sale_items', 'maintenance_part_id'),
            'have_commission' => [
                'products' => Schema::hasColumn('products', 'have_commission'),
                'spare_parts' => Schema::hasColumn('spare_parts', 'have_commission'),
                'maintenance_parts' => Schema::hasTable('maintenance_parts')
                    && Schema::hasColumn('maintenance_parts', 'have_commission'),
                'bike_for_sale' => Schema::hasColumn('bike_for_sale', 'have_commission'),
                'maintenance_services' => Schema::hasColumn('maintenance_services', 'have_commission'),
            ],
            'seller_per_type_rates' => $this->sellerPerTypeRateColumnsExist(),
            'seller_legacy_rate' => Schema::hasColumn('sellers', 'commission_rate'),
        ];
    }

    private function sellerPerTypeRateColumnsExist(): bool
    {
        foreach ($this->sellerRateColumns() as $column) {
            if (! Schema::hasColumn('sellers', $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function sellerRateColumns(): array
    {
        return [
            'products_commission_rate',
            'spare_parts_commission_rate',
            'maintenance_parts_commission_rate',
            'bikes_for_sale_commission_rate',
            'maintenance_services_commission_rate',
        ];
    }

    /**
     * @return list<array{condition: string, expression: string}>
     */
    private function eligibleLineBranches(): array
    {
        $lineSubtotal = $this->lineSubtotalSql();

        return $this->mapLineTypeBranches(
            fn (string $condition): string => $condition,
            fn (string $condition): string => $condition.' THEN '.$lineSubtotal,
        );
    }

    /**
     * @return list<array{condition: string, expression: string}>
     */
    private function commissionAmountBranches(): array
    {
        $lineSubtotal = $this->lineSubtotalSql();

        return $this->mapLineTypeBranches(
            fn (string $condition): string => $condition,
            fn (string $condition, string $rateSql): string => $condition.' THEN '.$lineSubtotal.' * '.$rateSql.' / 100',
        );
    }

    /**
     * @return list<array{condition: string, expression: string}>
     */
    private function mapLineTypeBranches(callable $mapCondition, callable $mapExpression): array
    {
        $branches = [];

        foreach ($this->lineTypes() as $lineType) {
            if ($lineType['key'] === 'maintenance_parts' && ! $this->commissionSchema()['maintenance_parts']) {
                continue;
            }

            $condition = $this->lineEligibilityCondition(
                $lineType['foreign_key'],
                $lineType['join_alias'],
                $lineType['have_commission_key'],
            );

            $branches[] = [
                'condition' => $mapCondition($condition),
                'expression' => $mapExpression($condition, $this->sellerRateSql($lineType['rate_column'])),
            ];
        }

        return $branches;
    }

    /**
     * @return list<array{key: string, foreign_key: string, join_alias: string, have_commission_key: string, rate_column: string}>
     */
    private function lineTypes(): array
    {
        return [
            [
                'key' => 'products',
                'foreign_key' => 'product_id',
                'join_alias' => 'products',
                'have_commission_key' => 'products',
                'rate_column' => 'products_commission_rate',
            ],
            [
                'key' => 'spare_parts',
                'foreign_key' => 'spare_part_id',
                'join_alias' => 'spare_parts',
                'have_commission_key' => 'spare_parts',
                'rate_column' => 'spare_parts_commission_rate',
            ],
            [
                'key' => 'maintenance_parts',
                'foreign_key' => 'maintenance_part_id',
                'join_alias' => 'maintenance_parts',
                'have_commission_key' => 'maintenance_parts',
                'rate_column' => 'maintenance_parts_commission_rate',
            ],
            [
                'key' => 'bike_for_sale',
                'foreign_key' => 'bike_for_sale_id',
                'join_alias' => 'bike_for_sale',
                'have_commission_key' => 'bike_for_sale',
                'rate_column' => 'bikes_for_sale_commission_rate',
            ],
            [
                'key' => 'maintenance_services',
                'foreign_key' => 'maintenance_service_id',
                'join_alias' => 'maintenance_services',
                'have_commission_key' => 'maintenance_services',
                'rate_column' => 'maintenance_services_commission_rate',
            ],
        ];
    }

    private function lineEligibilityCondition(
        string $foreignKey,
        string $joinAlias,
        string $haveCommissionKey,
    ): string {
        $itemCondition = "sale_items.{$foreignKey} IS NOT NULL";

        if ($this->commissionSchema()['have_commission'][$haveCommissionKey] ?? false) {
            return "{$itemCondition} AND COALESCE({$joinAlias}.have_commission, 0) = 1";
        }

        return $itemCondition;
    }

    private function sellerRateSql(string $rateColumn): string
    {
        $schema = $this->commissionSchema();

        if ($schema['seller_per_type_rates']) {
            return "COALESCE(sellers.{$rateColumn}, 0)";
        }

        if ($schema['seller_legacy_rate']) {
            return 'COALESCE(sellers.commission_rate, 0)';
        }

        return '0';
    }

    /**
     * @param  list<array{condition: string, expression: string}>  $branches
     */
    private function buildCaseSql(array $branches): string
    {
        if ($branches === []) {
            return '0';
        }

        $sql = "CASE\n";

        foreach ($branches as $branch) {
            $sql .= "            WHEN {$branch['expression']}\n";
        }

        return $sql."            ELSE 0\n        END";
    }

    private function rawLineSubtotal(SaleItem $item): float
    {
        $qty = max(0, $item->qty - $item->returned_qty);

        return max(0.0, ((float) $item->selling_price - (float) $item->discount) * $qty);
    }

    private function itemHasCommission(SaleItem $item): bool
    {
        return match (true) {
            ! is_null($item->product_id) => $this->modelHasCommission($item->product?->have_commission ?? null),
            ! is_null($item->spare_part_id) => $this->modelHasCommission($item->sparePart?->have_commission ?? null),
            ! is_null($item->maintenance_part_id) => $this->modelHasCommission($item->maintenancePart?->have_commission ?? null),
            ! is_null($item->bike_for_sale_id) => $this->modelHasCommission($item->bikeForSale?->have_commission ?? null),
            ! is_null($item->maintenance_service_id) => $this->modelHasCommission($item->maintenanceService?->have_commission ?? null),
            default => false,
        };
    }

    private function modelHasCommission(?bool $haveCommission): bool
    {
        return $haveCommission ?? true;
    }

    private function resolveCommissionRate(SaleItem $item, Seller $seller): float
    {
        $schema = $this->commissionSchema();

        if (! $schema['seller_per_type_rates']) {
            if ($schema['seller_legacy_rate']) {
                return (float) $seller->commission_rate;
            }

            return 0.0;
        }

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
