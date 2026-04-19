<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleAdjustment;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Customer $otherCustomer;
    private Seller $seller;
    private PaymentMethod $paymentMethod;
    private Product $product;
    private SparePart $sparePart;
    private SparePart $replacementSparePart;
    private SparePart $compatibleUniversalSparePart;
    private SparePart $incompatibleSparePart;
    private MaintenanceService $maintenanceService;
    private BikeBlueprint $bikeBlueprint;
    private \App\Models\BikeForSale $bike;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Omar',
            'email' => 'omar@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->customer = Customer::create([
            'name' => 'Ahmed Saleh',
            'phone' => '01000000001',
        ]);

        $this->otherCustomer = Customer::create([
            'name' => 'Sara Nabil',
            'phone' => '01000000002',
        ]);

        $this->seller = Seller::create([
            'name' => 'Main Seller',
            'phone' => '01111111111',
            'commission_rate' => 5,
        ]);

        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);

        $productBrand = Brand::create(['name' => 'Product Brand', 'type' => 'products']);
        $spareBrand = Brand::create(['name' => 'Spare Brand', 'type' => 'spare_parts']);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'type' => 'bikes']);

        $productCategory = ProductCategory::create(['name' => 'Accessories']);
        $spareCategory = SparePartCategory::create(['name' => 'Brakes']);
        $serviceSector = MaintenanceServiceSector::create(['name' => 'Workshop']);

        $this->bikeBlueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Falcon 250',
            'year' => 2025,
        ]);

        $this->product = Product::create([
            'name' => 'Helmet',
            'sku' => 'PR-001',
            'stock_quantity' => 10,
            'low_stock_alarm' => 1,
            'products_category_id' => $productCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'brand_id' => $productBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 20,
        ]);

        $this->sparePart = SparePart::create([
            'name' => 'Brake Pad',
            'sku' => 'SP-001',
            'stock_quantity' => 8,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $spareCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'brand_id' => $spareBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 10,
            'universal' => false,
        ]);

        $this->replacementSparePart = SparePart::create([
            'name' => 'Premium Brake Pad',
            'sku' => 'SP-002',
            'stock_quantity' => 6,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $spareCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 80,
            'sale_price' => 150,
            'brand_id' => $spareBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 15,
            'universal' => false,
        ]);

        $this->compatibleUniversalSparePart = SparePart::create([
            'name' => 'Universal Oil Filter',
            'sku' => 'SP-003',
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $spareCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 40,
            'sale_price' => 90,
            'brand_id' => $spareBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
            'universal' => true,
        ]);

        $this->incompatibleSparePart = SparePart::create([
            'name' => 'Chain Kit',
            'sku' => 'SP-004',
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $spareCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 30,
            'sale_price' => 70,
            'brand_id' => $spareBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
            'universal' => false,
        ]);

        $this->sparePart->bikeBlueprints()->attach($this->bikeBlueprint->id);
        $this->replacementSparePart->bikeBlueprints()->attach($this->bikeBlueprint->id);

        $this->maintenanceService = MaintenanceService::create([
            'name' => 'Full Tune Up',
            'currency_pricing' => 'EGP',
            'service_price' => 300,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 50,
            'maintenance_service_sector_id' => $serviceSector->id,
        ]);

        $this->bike = \App\Models\BikeForSale::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 4000,
            'sale_price' => 5000,
            'status' => 'available',
            'max_discount_type' => 'fixed',
            'max_discount_value' => 100,
            'vin' => 'VIN-001',
            'mileage' => 10,
        ]);
    }

    public function test_create_sale_with_mixed_items_and_logs_adjustment(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/sales', $this->mixedSalePayload());

        $response->assertCreated()
            ->assertJsonPath('customer.id', $this->customer->id)
            ->assertJsonPath('items.0.status', 'active');

        $saleId = $response->json('id');

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'created',
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'status' => SaleItem::STATUS_ACTIVE,
            'returned_qty' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock_quantity' => 8,
        ]);

        $this->assertDatabaseHas('spare_parts', [
            'id' => $this->sparePart->id,
            'stock_quantity' => 7,
        ]);

        $this->assertDatabaseHas('bike_for_sale', [
            'id' => $this->bike->id,
            'status' => 'sold',
        ]);

        $this->assertDatabaseHas('histories', [
            'model_type' => Sale::class,
            'model_id' => $saleId,
            'action' => 'create',
        ]);
    }

    public function test_can_filter_sales_and_view_adjustments(): void
    {
        $saleOne = $this->createSaleThroughApi();
        $saleTwo = $this->createSaleThroughApi([
            'customer_id' => $this->otherCustomer->id,
        ]);

        Sale::whereKey($saleOne['id'])->update(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        $listResponse = $this->actingAs($this->admin)->getJson('/api/sales?' . http_build_query([
            'sale_id' => $saleTwo['id'],
            'customer_name' => 'Sara',
            'item_type' => 'product',
            'date_from' => now()->format('Y-m-d'),
        ]));

        $listResponse->assertOk()
            ->assertJsonPath('data.0.id', $saleTwo['id'])
            ->assertJsonCount(1, 'data');

        $showResponse = $this->actingAs($this->admin)->getJson("/api/sales/{$saleTwo['id']}");
        $showResponse->assertOk()
            ->assertJsonPath('adjustments.0.action_type', 'created');

        $adjustmentsResponse = $this->actingAs($this->admin)->getJson("/api/sales/{$saleTwo['id']}/adjustments");
        $adjustmentsResponse->assertOk()
            ->assertJsonPath('data.0.action_type', 'created');
    }

    public function test_catalog_items_support_filters_and_spare_part_compatibility(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/sales/catalog-items?' . http_build_query([
            'type' => ['spare_part'],
            'compatible_with_blueprint_id' => $this->bikeBlueprint->id,
            'in_stock_only' => true,
        ]));

        $response->assertOk();

        $displayNames = collect($response->json('data'))->pluck('display_name')->all();

        $this->assertContains('Brake Pad', $displayNames);
        $this->assertContains('Premium Brake Pad', $displayNames);
        $this->assertContains('Universal Oil Filter', $displayNames);
        $this->assertNotContains('Chain Kit', $displayNames);
    }

    public function test_can_update_sale_header_and_manage_sale_items(): void
    {
        $sale = $this->createSaleThroughApi();
        $saleId = $sale['id'];
        $productItemId = $this->findSaleItemIdByType($sale, 'product');
        $serviceItemId = $this->findSaleItemIdByType($sale, 'maintenance_service');

        $updateSaleResponse = $this->actingAs($this->admin)->patchJson("/api/sales/{$saleId}", [
            'status' => 'partial',
            'shipping_fee' => 60,
        ]);

        $updateSaleResponse->assertOk()
            ->assertJsonPath('status', 'partial')
            ->assertJsonPath('shipping_fee', 60);

        $addItemResponse = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/items", [
            'product_id' => $this->product->id,
            'selling_price' => 200,
            'discount' => 0,
            'qty' => 1,
        ]);

        $addItemResponse->assertOk();

        $updateItemResponse = $this->actingAs($this->admin)->patchJson("/api/sales/{$saleId}/items/{$productItemId}", [
            'qty' => 3,
        ]);

        $updateItemResponse->assertOk();

        $removeItemResponse = $this->actingAs($this->admin)->deleteJson("/api/sales/{$saleId}/items/{$serviceItemId}");
        $removeItemResponse->assertOk();

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_added',
        ]);

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_updated',
        ]);

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_removed',
        ]);
    }

    public function test_can_return_sale_items_and_restore_stock(): void
    {
        $sale = $this->createSaleThroughApi([
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 0,
                    'qty' => 3,
                ],
            ],
        ]);

        $saleId = $sale['id'];
        $productItemId = $this->findSaleItemIdByType($sale, 'product');

        $partialReturn = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/returns", [
            'sale_item_id' => $productItemId,
            'qty' => 2,
            'notes' => 'Customer returned two items',
        ]);

        $partialReturn->assertOk()
            ->assertJsonPath('items.0.returned_qty', 2)
            ->assertJsonPath('items.0.status', SaleItem::STATUS_PARTIALLY_RETURNED);

        $fullReturn = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/returns", [
            'sale_item_id' => $productItemId,
            'qty' => 1,
        ]);

        $fullReturn->assertOk()
            ->assertJsonPath('items.0.status', SaleItem::STATUS_RETURNED)
            ->assertJsonPath('items.0.remaining_qty', 0);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock_quantity' => 10,
        ]);

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_returned',
            'refund_amount' => 400,
        ]);
    }

    public function test_can_exchange_items_and_track_extra_amount_due(): void
    {
        $sale = $this->createSaleThroughApi([
            'items' => [
                [
                    'spare_part_id' => $this->sparePart->id,
                    'selling_price' => 100,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ]);

        $saleId = $sale['id'];
        $saleItemId = $this->findSaleItemIdByType($sale, 'spare_part');

        $response = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/exchanges", [
            'sale_item_id' => $saleItemId,
            'qty' => 1,
            'replacement' => [
                'spare_part_id' => $this->replacementSparePart->id,
                'selling_price' => 150,
                'discount' => 0,
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_exchanged',
            'extra_amount_due' => 50,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'id' => $saleItemId,
            'status' => SaleItem::STATUS_EXCHANGED,
            'returned_qty' => 1,
        ]);

        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $saleId,
            'spare_part_id' => $this->replacementSparePart->id,
            'replaced_from_sale_item_id' => $saleItemId,
        ]);
    }

    public function test_can_exchange_items_and_track_refund_amount(): void
    {
        $sale = $this->createSaleThroughApi([
            'items' => [
                [
                    'spare_part_id' => $this->replacementSparePart->id,
                    'selling_price' => 150,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ]);

        $saleId = $sale['id'];
        $saleItemId = $this->findSaleItemIdByType($sale, 'spare_part');

        $response = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/exchanges", [
            'sale_item_id' => $saleItemId,
            'qty' => 1,
            'replacement' => [
                'spare_part_id' => $this->sparePart->id,
                'selling_price' => 100,
                'discount' => 0,
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $saleId,
            'action_type' => 'item_exchanged',
            'refund_amount' => 50,
        ]);
    }

    private function createSaleThroughApi(?array $override = null): array
    {
        $payload = $this->mixedSalePayload(
            bikeId: $this->createAvailableBike()->id,
        );

        if ($override) {
            foreach ($override as $key => $value) {
                $payload[$key] = $value;
            }
        }

        return $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertCreated()
            ->json();
    }

    private function findSaleItemIdByType(array $sale, string $type): int
    {
        return (int) collect($sale['items'])->firstWhere('item_type', $type)['id'];
    }

    private function mixedSalePayload(?int $customerId = null, ?int $bikeId = null): array
    {
        return [
            'customer_id' => $customerId ?? $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 50,
            'discount' => 20,
            'is_maintenance' => false,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 0,
                    'qty' => 2,
                ],
                [
                    'spare_part_id' => $this->sparePart->id,
                    'selling_price' => 100,
                    'discount' => 0,
                    'qty' => 1,
                ],
                [
                    'bike_for_sale_id' => $bikeId ?? $this->bike->id,
                    'selling_price' => 5000,
                    'discount' => 0,
                    'qty' => 1,
                ],
                [
                    'maintenance_service_id' => $this->maintenanceService->id,
                    'selling_price' => 300,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ];
    }

    private function createAvailableBike(): \App\Models\BikeForSale
    {
        static $bikeCounter = 2;

        return \App\Models\BikeForSale::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 4000,
            'sale_price' => 5000,
            'status' => 'available',
            'max_discount_type' => 'fixed',
            'max_discount_value' => 100,
            'vin' => 'VIN-' . str_pad((string) $bikeCounter++, 3, '0', STR_PAD_LEFT),
            'mileage' => 10,
        ]);
    }
}
