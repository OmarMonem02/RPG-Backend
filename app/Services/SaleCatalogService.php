<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\MaintenanceService;
use App\Models\Product;
use App\Models\SparePart;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class SaleCatalogService
{
    public function catalog(array $filters): LengthAwarePaginator
    {
        $types = $filters['type'] ?? ['product', 'spare_part', 'bike', 'maintenance_service'];
        if ($types === []) {
            $types = ['product', 'spare_part', 'bike', 'maintenance_service'];
        }

        $items = collect();

        foreach ($types as $type) {
            $items = $items->merge(match ($type) {
                'product' => $this->productItems($filters),
                'spare_part' => $this->sparePartItems($filters),
                'bike' => $this->bikeItems($filters),
                'maintenance_service' => $this->maintenanceServiceItems($filters),
                default => collect(),
            });
        }

        $items = $items
            ->sortBy([
                fn (array $item) => strtolower($item['display_name']),
                fn (array $item) => strtolower($item['item_type']),
                fn (array $item) => $item['id'],
            ])
            ->values();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, (int) ($filters['per_page'] ?? 20));

        return new Paginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    private function productItems(array $filters): Collection
    {
        $query = Product::query()->with(['brand', 'category']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $product) use ($search): void {
                $product
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('part_number', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('products_category_id', $filters['category_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency_pricing', strtoupper($filters['currency']));
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null) {
            $query->where('sale_price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null) {
            $query->where('sale_price', '<=', $filters['price_max']);
        }

        if (! empty($filters['in_stock_only'])) {
            $query->where('stock_quantity', '>', 0);
        }

        return $query->get()->map(fn (Product $product) => [
            'item_type' => 'product',
            'id' => $product->id,
            'display_name' => $product->name,
            'sku_or_vin' => $product->sku,
            'sale_price' => (float) $product->sale_price,
            'currency_pricing' => $product->currency_pricing,
            'stock_quantity' => (int) $product->stock_quantity,
            'is_available' => (int) $product->stock_quantity > 0,
            'brand' => $product->brand ? ['id' => $product->brand->id, 'name' => $product->brand->name] : null,
            'category' => $product->category ? ['id' => $product->category->id, 'name' => $product->category->name] : null,
            'sector' => null,
            'bike_blueprint' => null,
            'compatibility' => null,
        ]);
    }

    private function sparePartItems(array $filters): Collection
    {
        $query = SparePart::query()->with(['brand', 'category', 'bikeBlueprints.brand']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $part) use ($search): void {
                $part
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('part_number', 'like', '%' . $search . '%');
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (! empty($filters['category_id'])) {
            $query->where('spare_parts_category_id', $filters['category_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency_pricing', strtoupper($filters['currency']));
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null) {
            $query->where('sale_price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null) {
            $query->where('sale_price', '<=', $filters['price_max']);
        }

        if (! empty($filters['in_stock_only'])) {
            $query->where('stock_quantity', '>', 0);
        }

        if (! empty($filters['compatible_with_blueprint_id'])) {
            $blueprintId = (int) $filters['compatible_with_blueprint_id'];
            $query->where(function (Builder $part) use ($blueprintId): void {
                $part
                    ->where('universal', true)
                    ->orWhereHas('bikeBlueprints', fn (Builder $blueprints) => $blueprints->where('bike_blueprints.id', $blueprintId));
            });
        }

        if (! empty($filters['bike_blueprint_id'])) {
            $query->whereHas('bikeBlueprints', fn (Builder $blueprints) => $blueprints->where('bike_blueprints.id', $filters['bike_blueprint_id']));
        }

        return $query->get()->map(function (SparePart $part): array {
            return [
                'item_type' => 'spare_part',
                'id' => $part->id,
                'display_name' => $part->name,
                'sku_or_vin' => $part->sku,
                'sale_price' => (float) $part->sale_price,
                'currency_pricing' => $part->currency_pricing,
                'stock_quantity' => (int) $part->stock_quantity,
                'is_available' => (int) $part->stock_quantity > 0,
                'brand' => $part->brand ? ['id' => $part->brand->id, 'name' => $part->brand->name] : null,
                'category' => $part->category ? ['id' => $part->category->id, 'name' => $part->category->name] : null,
                'sector' => null,
                'bike_blueprint' => null,
                'compatibility' => [
                    'universal' => (bool) $part->universal,
                    'bike_blueprints' => $part->bikeBlueprints->map(fn ($blueprint) => [
                        'id' => $blueprint->id,
                        'model' => $blueprint->model,
                        'year' => $blueprint->year,
                        'brand' => $blueprint->brand ? $blueprint->brand->name : null,
                    ])->values(),
                ],
            ];
        });
    }

    private function bikeItems(array $filters): Collection
    {
        $query = BikeForSale::query()->with(['bikeBlueprint.brand']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $bike) use ($search): void {
                $bike
                    ->where('vin', 'like', '%' . $search . '%')
                    ->orWhereHas('bikeBlueprint', fn (Builder $blueprint) => $blueprint->where('model', 'like', '%' . $search . '%'))
                    ->orWhereHas('bikeBlueprint.brand', fn (Builder $brand) => $brand->where('name', 'like', '%' . $search . '%'));
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->whereHas('bikeBlueprint', fn (Builder $blueprint) => $blueprint->where('brand_id', $filters['brand_id']));
        }

        if (! empty($filters['bike_blueprint_id'])) {
            $query->where('bike_blueprint_id', $filters['bike_blueprint_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency_pricing', strtoupper($filters['currency']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null) {
            $query->where('sale_price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null) {
            $query->where('sale_price', '<=', $filters['price_max']);
        }

        if (! empty($filters['in_stock_only'])) {
            $query->where('status', 'available');
        }

        return $query->get()->map(function (BikeForSale $bike): array {
            return [
                'item_type' => 'bike',
                'id' => $bike->id,
                'display_name' => trim(($bike->bikeBlueprint?->brand?->name ?? '') . ' ' . ($bike->bikeBlueprint?->model ?? 'Bike')),
                'sku_or_vin' => $bike->vin,
                'sale_price' => (float) $bike->sale_price,
                'currency_pricing' => $bike->currency_pricing,
                'stock_quantity' => 1,
                'is_available' => $bike->status === 'available',
                'brand' => $bike->bikeBlueprint?->brand ? ['id' => $bike->bikeBlueprint->brand->id, 'name' => $bike->bikeBlueprint->brand->name] : null,
                'category' => null,
                'sector' => null,
                'bike_blueprint' => $bike->bikeBlueprint ? [
                    'id' => $bike->bikeBlueprint->id,
                    'model' => $bike->bikeBlueprint->model,
                    'year' => $bike->bikeBlueprint->year,
                ] : null,
                'compatibility' => null,
                'status' => $bike->status,
            ];
        });
    }

    private function maintenanceServiceItems(array $filters): Collection
    {
        $query = MaintenanceService::query()->with('sector');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $service) use ($search): void {
                $service
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhereHas('sector', fn (Builder $sector) => $sector->where('name', 'like', '%' . $search . '%'));
            });
        }

        if (! empty($filters['sector_id'])) {
            $query->where('maintenance_service_sector_id', $filters['sector_id']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency_pricing', strtoupper($filters['currency']));
        }

        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null) {
            $query->where('service_price', '>=', $filters['price_min']);
        }

        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null) {
            $query->where('service_price', '<=', $filters['price_max']);
        }

        return $query->get()->map(fn (MaintenanceService $service) => [
            'item_type' => 'maintenance_service',
            'id' => $service->id,
            'display_name' => $service->name,
            'sku_or_vin' => null,
            'sale_price' => (float) $service->service_price,
            'currency_pricing' => $service->currency_pricing,
            'stock_quantity' => null,
            'is_available' => true,
            'brand' => null,
            'category' => null,
            'sector' => $service->sector ? ['id' => $service->sector->id, 'name' => $service->sector->name] : null,
            'bike_blueprint' => null,
            'compatibility' => null,
        ]);
    }
}
