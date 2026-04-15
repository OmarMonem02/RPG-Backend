<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntityRequest;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\CustomerSale;
use App\Models\Delivery;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\SparePartCategory;
use App\Models\TicketItem;
use App\Models\Setting;
use App\Models\TicketTask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityController extends Controller
{
    private function resolve(string $entity): Model
    {
        return new (match ($entity) {
            'sellers' => Seller::class,
            'customers' => Customer::class,
            'products' => Product::class,
            'product_categories' => ProductCategory::class,
            'spare_part_categories' => SparePartCategory::class,
            'maintenance_services' => MaintenanceService::class,
            'maintenance_service_sectors' => MaintenanceServiceSector::class,
            'brands' => Brand::class,
            'bike_for_sale' => BikeForSale::class,
            'customer_bikes' => CustomerBike::class,
            'customer_sale' => CustomerSale::class,
            'sale_items' => SaleItem::class,
            'payment_methods' => PaymentMethod::class,
            'deliveries' => Delivery::class,
            'ticket_tasks' => TicketTask::class,
            'ticket_items' => TicketItem::class,
            'settings' => Setting::class,
            default => abort(404, 'Entity not supported'),
        })();
    }

    public function index(Request $request, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);
        $query = $model->newQuery();

        // Apply common filters based on entity type
        $search = $request->query('search');
        $brandId = $request->query('brand_id');
        $categoryId = $request->query('category_id');
        $sectorId = $request->query('sector_id');
        $priceRange = $request->query('price_range');
        $currency = $request->query('currency');
        $status = $request->query('status');
        $blueprintId = $request->query('blueprint_id');
        $year = $request->query('year');
        $lowStock = $request->query('low_stock');

        // Validate and parse price range (format: "min:max")
        $minPrice = null;
        $maxPrice = null;
        if ($priceRange) {
            if (strpos($priceRange, ':') !== false) {
                $parts = explode(':', $priceRange, 2);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $minPrice = floatval($parts[0]);
                    $maxPrice = floatval($parts[1]);
                    if ($minPrice > $maxPrice) {
                        return response()->json(['error' => 'Invalid price_range: min price must be less than or equal to max price'], 422);
                    }
                } else {
                    return response()->json(['error' => 'Invalid price_range format. Use "min:max" (e.g., "100:500")'], 422);
                }
            } else {
                return response()->json(['error' => 'Invalid price_range format. Use "min:max" (e.g., "100:500")'], 422);
            }
        }

        // Validate currency if provided
        $validCurrencies = ['EGP', 'USD'];
        if ($currency && !in_array(strtoupper($currency), $validCurrencies)) {
            return response()->json(['error' => 'Invalid currency. Supported: EGP, USD'], 422);
        }

        // Apply filters based on entity type
        switch ($entity) {
            case 'products':
                if ($search) $query = $query->search($search);
                if ($categoryId) $query = $query->byCategory($categoryId);
                if ($brandId) $query = $query->byBrand($brandId);
                if ($minPrice !== null || $maxPrice !== null) $query = $query->byPrice($minPrice, $maxPrice);
                if ($currency) $query = $query->byCurrency(strtoupper($currency));
                break;

            case 'spare_part_categories':
            case 'spare_parts':
                if ($search) $query = $query->search($search);
                if ($categoryId) $query = $query->byCategory($categoryId);
                if ($brandId) $query = $query->byBrand($brandId);
                if ($minPrice !== null || $maxPrice !== null) $query = $query->byPrice($minPrice, $maxPrice);
                if ($currency) $query = $query->byCurrency(strtoupper($currency));
                if ($lowStock) $query = $query->lowStock();
                break;

            case 'brands':
                if ($search) $query = $query->search($search);
                break;

            case 'bike_for_sale':
                if ($search) $query = $query->search($search);
                if ($status) $query = $query->byStatus($status);
                if ($minPrice !== null || $maxPrice !== null) $query = $query->byPrice($minPrice, $maxPrice);
                if ($blueprintId) $query = $query->byBlueprint($blueprintId);
                if ($currency) $query = $query->byCurrency(strtoupper($currency));
                break;

            case 'bike_blueprints':
                if ($search) $query = $query->search($search);
                if ($brandId) $query = $query->byBrand($brandId);
                if ($year) $query = $query->byYear($year);
                break;

            case 'maintenance_services':
                if ($search) $query = $query->search($search);
                if ($sectorId) $query = $query->bySector($sectorId);
                if ($minPrice !== null || $maxPrice !== null) $query = $query->byPrice($minPrice, $maxPrice);
                if ($currency) $query = $query->byCurrency(strtoupper($currency));
                break;

            case 'customers':
                if ($search) $query = $query->search($search);
                break;
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20)));
    }

    public function store(EntityRequest $request, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);
        $validated = $request->validated() + $request->except(['entity', 'id']);
        $record = $model->newQuery()->create($validated);

        return response()->json($record, 201);
    }

    public function show($id, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);

        if ($entity === 'settings' && !is_numeric($id)) {
            $record = $model->newQuery()->where('key', $id)->firstOrFail();
        } else {
            $record = $model->newQuery()->findOrFail($id);
        }

        return response()->json($record);
    }

    public function update(EntityRequest $request, $id, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);

        if ($entity === 'settings' && !is_numeric($id)) {
            $record = $model->newQuery()->where('key', $id)->firstOrFail();
        } else {
            $record = $model->newQuery()->findOrFail($id);
        }

        $data = $request->validated() ?: $request->except(['entity', 'id']);
        \Illuminate\Support\Facades\Log::info("Updating {$entity} record", ['id' => $record->id, 'data' => $data]);
        
        $record->fill($data);
        $saved = $record->save();
        
        \Illuminate\Support\Facades\Log::info("Save result", ['saved' => $saved, 'changes' => $record->getChanges()]);

        return response()->json($record);
    }

    public function destroy($id, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);

        if ($entity === 'settings' && !is_numeric($id)) {
            $record = $model->newQuery()->where('key', $id)->firstOrFail();
        } else {
            $record = $model->newQuery()->findOrFail($id);
        }

        $record->delete();

        return response()->json([], 204);
    }
}

