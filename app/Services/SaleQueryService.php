<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SaleQueryService
{
    /**
     * @var array<string, string>
     */
    private const ITEM_TYPE_COLUMNS = [
        'product' => 'product_id',
        'spare_part' => 'spare_part_id',
        'maintenance_service' => 'maintenance_service_id',
        'bike' => 'bike_for_sale_id',
    ];

    public function __construct(private readonly SalePresenterService $presenter)
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Sale::query()
            ->with(SalePresenterService::SALE_RELATIONS)
            ->latest();

        if (! empty($filters['sale_id'])) {
            $query->whereKey($filters['sale_id']);
        }

        if (! empty($filters['customer_name'])) {
            $query->whereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%' . $filters['customer_name'] . '%'));
        }

        if (! empty($filters['customer_phone'])) {
            $query->whereHas('customer', fn (Builder $customer) => $customer->where('phone', 'like', '%' . $filters['customer_phone'] . '%'));
        }

        foreach (['user_id', 'seller_id', 'payment_method_id', 'type', 'status', 'delivery_status'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        if (array_key_exists('is_maintenance', $filters) && $filters['is_maintenance'] !== null) {
            $query->where('is_maintenance', (bool) $filters['is_maintenance']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (array_key_exists('total_min', $filters) && $filters['total_min'] !== null) {
            $query->where('total', '>=', $filters['total_min']);
        }

        if (array_key_exists('total_max', $filters) && $filters['total_max'] !== null) {
            $query->where('total', '<=', $filters['total_max']);
        }

        if (! empty($filters['item_type'])) {
            $column = self::ITEM_TYPE_COLUMNS[$filters['item_type']] ?? null;
            if ($column) {
                $query->whereHas('items', fn (Builder $items) => $items->whereNotNull($column));
            }
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $saleQuery) use ($search): void {
                $hasStarted = false;

                if (is_numeric($search)) {
                    $saleQuery->where('id', (int) $search);
                    $hasStarted = true;
                }

                $relationMethod = $hasStarted ? 'orWhereHas' : 'whereHas';

                $saleQuery
                    ->{$relationMethod}('customer', fn (Builder $customer) => $customer
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('phone', 'like', '%' . $search . '%'))
                    ->orWhereHas('items.product', fn (Builder $product) => $product
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%')
                        ->orWhere('part_number', 'like', '%' . $search . '%'))
                    ->orWhereHas('items.sparePart', fn (Builder $part) => $part
                        ->where('name', 'like', '%' . $search . '%')
                        ->orWhere('sku', 'like', '%' . $search . '%')
                        ->orWhere('part_number', 'like', '%' . $search . '%'))
                    ->orWhereHas('items.maintenanceService', fn (Builder $service) => $service->where('name', 'like', '%' . $search . '%'))
                    ->orWhereHas('items.bikeForSale', fn (Builder $bike) => $bike
                        ->where('vin', 'like', '%' . $search . '%')
                        ->orWhereHas('bikeBlueprint', fn (Builder $blueprint) => $blueprint->where('model', 'like', '%' . $search . '%')));
            });
        }

        return $query
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->through(fn (Sale $sale) => $this->presenter->serializeSale($sale));
    }
}
