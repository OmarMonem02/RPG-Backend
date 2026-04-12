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
            'maintenance_service_sectors' => MaintenanceServiceSector::class,
            'brands' => Brand::class,
            'maintenance_services' => MaintenanceService::class,
            'bike_for_sale' => BikeForSale::class,
            'customer_bikes' => CustomerBike::class,
            'customer_sale' => CustomerSale::class,
            'sale_items' => SaleItem::class,
            'payment_methods' => PaymentMethod::class,
            'deliveries' => Delivery::class,
            'ticket_tasks' => TicketTask::class,
            'ticket_items' => TicketItem::class,
            default => abort(404, 'Entity not supported'),
        })();
    }

    public function index(Request $request, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);

        return response()->json($model->newQuery()->paginate((int) $request->query('per_page', 20)));
    }

    public function store(EntityRequest $request, string $entity): JsonResponse
    {
        $model = $this->resolve($entity);
        $validated = $request->validated() + $request->except(['entity', 'id']);
        $record = $model->newQuery()->create($validated);

        return response()->json($record, 201);
    }

    public function show(string $entity, int $id): JsonResponse
    {
        $model = $this->resolve($entity);
        $record = $model->newQuery()->findOrFail($id);

        return response()->json($record);
    }

    public function update(EntityRequest $request, string $entity, int $id): JsonResponse
    {
        $model = $this->resolve($entity);
        $record = $model->newQuery()->findOrFail($id);
        $record->update($request->validated() + $request->except(['entity', 'id']));

        return response()->json($record);
    }

    public function destroy(string $entity, int $id): JsonResponse
    {
        $model = $this->resolve($entity);
        $record = $model->newQuery()->findOrFail($id);
        $record->delete();

        return response()->json([], 204);
    }
}

