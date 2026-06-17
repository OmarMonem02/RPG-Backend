<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\CaseInsensitiveLike;
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
        'maintenance_part' => 'maintenance_part_id',
        'maintenance_service' => 'maintenance_service_id',
        'bike' => 'bike_for_sale_id',
    ];

    /**
     * Eager loads for spreadsheet export (lighter than full list serialization).
     *
     * @var array<int, string>
     */
    private const EXPORT_RELATIONS = [
        'customer',
        'seller',
        'paymentMethod',
        'user',
        'items.product',
        'items.sparePart',
        'items.maintenanceService',
        'items.bikeForSale.bikeBlueprint.brand',
    ];

    public function __construct(private readonly SalePresenterService $presenter)
    {
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Sale::query()
            ->with(SalePresenterService::SALE_RELATIONS);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'newest');

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = array_key_exists('page', $filters) && $filters['page'] !== null
            ? max(1, (int) $filters['page'])
            : null;

        $paginator = $page !== null
            ? $query->paginate($perPage, ['*'], 'page', $page)
            : $query->paginate($perPage);

        return $paginator->through(fn (Sale $sale) => $this->presenter->serializeSale($sale));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportQuery(array $filters): Builder
    {
        $query = Sale::query()->with(self::EXPORT_RELATIONS);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['sale_id'])) {
            $query->whereKey($filters['sale_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['customer_name'])) {
            $query->whereHas('customer', function (Builder $customer) use ($filters) {
                CaseInsensitiveLike::where($customer, 'name', $filters['customer_name']);
            });
        }

        if (! empty($filters['customer_phone'])) {
            $query->whereHas('customer', function (Builder $customer) use ($filters) {
                CaseInsensitiveLike::where($customer, 'phone', $filters['customer_phone']);
            });
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
                    ->{$relationMethod}('customer', function (Builder $customer) use ($search) {
                        CaseInsensitiveLike::where($customer, 'name', $search);
                        CaseInsensitiveLike::orWhere($customer, 'phone', $search);
                    })
                    ->orWhereHas('seller', function (Builder $seller) use ($search) {
                        CaseInsensitiveLike::where($seller, 'name', $search);
                        CaseInsensitiveLike::orWhere($seller, 'phone', $search);
                    })
                    ->orWhereHas('items.product', function (Builder $product) use ($search) {
                        CaseInsensitiveLike::where($product, 'name', $search);
                        CaseInsensitiveLike::orWhere($product, 'sku', $search);
                        CaseInsensitiveLike::orWhere($product, 'part_number', $search);
                    })
                    ->orWhereHas('items.sparePart', function (Builder $part) use ($search) {
                        CaseInsensitiveLike::where($part, 'name', $search);
                        CaseInsensitiveLike::orWhere($part, 'sku', $search);
                        CaseInsensitiveLike::orWhere($part, 'part_number', $search);
                    })
                    ->orWhereHas('items.maintenanceService', function (Builder $service) use ($search) {
                        CaseInsensitiveLike::where($service, 'name', $search);
                    })
                    ->orWhereHas('items.bikeForSale', function (Builder $bike) use ($search) {
                        CaseInsensitiveLike::where($bike, 'vin', $search);
                        $bike->orWhereHas('bikeBlueprint', function (Builder $blueprint) use ($search) {
                            CaseInsensitiveLike::where($blueprint, 'model', $search);
                        });
                    });
            });
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'highest' => $query->orderByDesc('total')->orderByDesc('id'),
            'lowest' => $query->orderBy('total')->orderBy('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
