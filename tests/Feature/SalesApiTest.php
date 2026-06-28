<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerAddress;
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
use Tests\Support\SellerTestFactory;
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

        $this->seller = SellerTestFactory::create([
            'name' => 'Main Seller',
            'phone' => '01111111111',
            'commission_rate' => 5,
        ]);

        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);

        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $spareBrand = Brand::create(['name' => 'Spare Brand', 'types' => ['spare_parts']]);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'types' => ['bikes']]);

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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
            'sale_currency' => 'EGP',
            'service_price' => 300,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 50,
            'maintenance_service_sector_id' => $serviceSector->id,
        ]);

        $this->bike = \App\Models\BikeForSale::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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

    public function test_sales_list_supports_customer_seller_search_and_global_sort(): void
    {
        $secondarySeller = SellerTestFactory::create([
            'name' => 'Night Shift Seller',
            'phone' => '01222222222',
            'commission_rate' => 7,
        ]);

        $lowSale = $this->createSaleThroughApi([
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 100,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ]);
        $highSale = $this->createSaleThroughApi([
            'customer_id' => $this->otherCustomer->id,
            'seller_id' => $secondarySeller->id,
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 500,
                    'discount' => 0,
                    'qty' => 2,
                ],
            ],
        ]);

        $customerFilterResponse = $this->actingAs($this->admin)->getJson('/api/sales?' . http_build_query([
            'customer_id' => $this->otherCustomer->id,
        ]));
        $customerFilterResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $highSale['id']);

        $sellerSearchResponse = $this->actingAs($this->admin)->getJson('/api/sales?' . http_build_query([
            'search' => 'Night Shift',
        ]));
        $sellerSearchResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $highSale['id']);

        $highestResponse = $this->actingAs($this->admin)->getJson('/api/sales?' . http_build_query([
            'sort' => 'highest',
        ]));
        $highestResponse->assertOk()
            ->assertJsonPath('data.0.id', $highSale['id']);

        $lowestResponse = $this->actingAs($this->admin)->getJson('/api/sales?' . http_build_query([
            'sort' => 'lowest',
        ]));
        $lowestResponse->assertOk()
            ->assertJsonPath('data.0.id', $lowSale['id']);
    }

    public function test_overall_sale_discount_requires_admin_role(): void
    {
        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        unset($payload['admin_password']);

        $staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($staff)
            ->postJson('/api/sales', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_approval_request_id']);

        $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertCreated();

        $payloadWithWrongPassword = $this->mixedSalePayload(
            bikeId: $this->createAvailableBike()->id,
        );
        unset($payloadWithWrongPassword['admin_password']);
        $payloadWithWrongPassword['admin_password'] = 'wrong-password';

        $this->actingAs($this->admin)
            ->postJson('/api/sales', $payloadWithWrongPassword)
            ->assertCreated();
    }

    public function test_rejects_invalid_sale_money_delivery_and_discount_values(): void
    {
        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['shipping_fee'] = -1;
        $payload['delivery_status'] = 'lost';
        $payload['discount'] = 0;
        $payload['items'][0]['selling_price'] = 100;
        $payload['items'][0]['discount'] = 125;

        $response = $this->actingAs($this->admin)->postJson('/api/sales', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'shipping_fee',
                'delivery_status',
                'items.0.discount',
            ]);
    }

    public function test_rejects_online_sale_without_customer_address(): void
    {
        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['type'] = 'online';
        $payload['status'] = 'pending';

        $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_address_id']);
    }

    public function test_accepts_delivery_sale_with_valid_customer_address(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => '10 Delivery Lane',
            'city' => 'Alexandria',
            'is_default' => true,
        ]);

        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['type'] = 'delivery';
        $payload['status'] = 'pending';
        $payload['customer_address_id'] = $address->id;

        $response = $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertCreated()
            ->assertJsonPath('customer_address_id', $address->id)
            ->assertJsonPath('customer_address.full_address', '10 Delivery Lane');

        $this->assertDatabaseHas('sales', [
            'id' => $response->json('id'),
            'customer_address_id' => $address->id,
        ]);
    }

    public function test_rejects_customer_address_that_does_not_belong_to_customer(): void
    {
        $otherAddress = CustomerAddress::create([
            'customer_id' => $this->otherCustomer->id,
            'full_address' => '99 Other Street',
            'city' => 'Cairo',
            'is_default' => true,
        ]);

        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['type'] = 'online';
        $payload['status'] = 'pending';
        $payload['customer_address_id'] = $otherAddress->id;

        $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_address_id']);
    }

    public function test_remote_only_filter_returns_online_and_delivery_excludes_site(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => '10 Delivery Lane',
            'city' => 'Alexandria',
            'is_default' => true,
        ]);

        $siteSale = $this->createSaleThroughApi(['type' => 'site']);

        $onlinePayload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $onlinePayload['type'] = 'online';
        $onlinePayload['status'] = 'pending';
        $onlinePayload['customer_address_id'] = $address->id;
        $onlineSale = $this->actingAs($this->admin)
            ->postJson('/api/sales', $onlinePayload)
            ->assertCreated()
            ->json();

        $deliveryPayload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $deliveryPayload['type'] = 'delivery';
        $deliveryPayload['status'] = 'pending';
        $deliveryPayload['customer_address_id'] = $address->id;
        $deliverySale = $this->actingAs($this->admin)
            ->postJson('/api/sales', $deliveryPayload)
            ->assertCreated()
            ->json();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/sales?' . http_build_query(['remote_only' => true, 'per_page' => 100]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($onlineSale['id'], $ids);
        $this->assertContains($deliverySale['id'], $ids);
        $this->assertNotContains($siteSale['id'], $ids);

        $stringFlagResponse = $this->actingAs($this->admin)
            ->getJson('/api/sales?remote_only=true&per_page=100');

        $stringFlagResponse->assertOk();
        $stringFlagIds = collect($stringFlagResponse->json('data'))->pluck('id')->all();
        $this->assertContains($onlineSale['id'], $stringFlagIds);
        $this->assertNotContains($siteSale['id'], $stringFlagIds);
    }

    public function test_can_update_remote_sale_delivery_fields_and_address(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => '10 Delivery Lane',
            'city' => 'Alexandria',
            'is_default' => true,
        ]);

        $newAddress = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => '22 New Harbor Road',
            'city' => 'Cairo',
            'is_default' => false,
        ]);

        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['type'] = 'delivery';
        $payload['status'] = 'pending';
        $payload['customer_address_id'] = $address->id;
        $payload['delivery_status'] = 'pending';

        $sale = $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertCreated()
            ->json();

        $saleId = $sale['id'];

        $this->actingAs($this->admin)
            ->patchJson("/api/sales/{$saleId}", [
                'delivery_status' => 'in-transit',
                'shipping_fee' => 75,
                'customer_address_id' => $newAddress->id,
            ])
            ->assertOk()
            ->assertJsonPath('delivery_status', 'in-transit')
            ->assertJsonPath('shipping_fee', 75)
            ->assertJsonPath('customer_address_id', $newAddress->id)
            ->assertJsonPath('customer_address.full_address', '22 New Harbor Road');

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'delivery_status' => 'in-transit',
            'customer_address_id' => $newAddress->id,
        ]);
    }

    public function test_rejects_customer_address_update_for_wrong_customer_on_remote_sale(): void
    {
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'full_address' => '10 Delivery Lane',
            'city' => 'Alexandria',
            'is_default' => true,
        ]);

        $otherAddress = CustomerAddress::create([
            'customer_id' => $this->otherCustomer->id,
            'full_address' => '99 Other Street',
            'city' => 'Cairo',
            'is_default' => true,
        ]);

        $payload = $this->mixedSalePayload(bikeId: $this->createAvailableBike()->id);
        $payload['type'] = 'delivery';
        $payload['status'] = 'pending';
        $payload['customer_address_id'] = $address->id;

        $saleId = $this->actingAs($this->admin)
            ->postJson('/api/sales', $payload)
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->admin)
            ->patchJson("/api/sales/{$saleId}", [
                'customer_address_id' => $otherAddress->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_address_id']);
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
            'shipping_fee' => 60,
        ]);

        $updateSaleResponse->assertOk()
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

    public function test_admin_can_view_sale_scoped_history_and_staff_cannot(): void
    {
        $sale = $this->createSaleThroughApi();
        $saleId = $sale['id'];

        $this->actingAs($this->admin)->patchJson("/api/sales/{$saleId}", [
            'shipping_fee' => 75,
        ])->assertOk();

        $this->assertDatabaseHas('histories', [
            'model_type' => Sale::class,
            'model_id' => $saleId,
            'action' => 'update',
        ]);

        $adminResponse = $this->actingAs($this->admin)->getJson("/api/sales/{$saleId}/history");
        $adminResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'action',
                        'entity_type',
                        'changes',
                        'user',
                    ],
                ],
                'meta' => ['current_page', 'last_page'],
            ]);

        $entries = collect($adminResponse->json('data'));
        $this->assertTrue($entries->contains(
            fn (array $row) => ($row['model_id'] ?? null) === $saleId
                && ($row['entity_type'] ?? null) === 'sale'
                && ($row['action'] ?? null) === 'update',
        ));
        $this->assertTrue($entries->contains(
            fn (array $row) => ($row['model_id'] ?? null) === $saleId
                && ($row['entity_type'] ?? null) === 'sale'
                && ($row['action'] ?? null) === 'create',
        ));

        $staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff-history@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($staff)
            ->getJson("/api/sales/{$saleId}/history")
            ->assertForbidden();
    }

    public function test_can_return_sale_items_and_restore_stock(): void
    {
        $sale = $this->createSaleThroughApi([
            'shipping_fee' => 0,
            'discount' => 0,
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
            ->assertJsonPath('items.0.status', SaleItem::STATUS_PARTIALLY_RETURNED)
            ->assertJsonPath('total', 200);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total' => 200,
        ]);

        $fullReturn = $this->actingAs($this->admin)->postJson("/api/sales/{$saleId}/returns", [
            'sale_item_id' => $productItemId,
            'qty' => 1,
        ]);

        $fullReturn->assertOk()
            ->assertJsonPath('items.0.status', SaleItem::STATUS_RETURNED)
            ->assertJsonPath('items.0.remaining_qty', 0)
            ->assertJsonPath('total', 0);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total' => 0,
        ]);

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
            'shipping_fee' => 0,
            'discount' => 0,
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

        $response->assertOk()
            ->assertJsonPath('total', 150);

        $this->assertDatabaseHas('sales', [
            'id' => $saleId,
            'total' => 150,
        ]);

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

    public function test_deleting_sale_logs_adjustment_and_restores_inventory(): void
    {
        $sale = $this->createSaleThroughApi([
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/sales/{$sale['id']}");

        $response->assertNoContent();

        $this->assertSoftDeleted('sales', ['id' => $sale['id']]);
        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'stock_quantity' => 10,
        ]);
        $this->assertDatabaseHas('sale_adjustments', [
            'sale_id' => $sale['id'],
            'action_type' => 'sale_deleted',
            'amount_delta' => -200,
        ]);
    }

    public function test_sales_export_returns_xlsx_for_admin(): void
    {
        $this->createSaleThroughApi();

        $response = $this->actingAs($this->admin)->get('/api/sales/export?format=xlsx');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString('sales_export_', (string) $response->headers->get('content-disposition'));
        $this->assertGreaterThan(100, strlen($response->streamedContent()));
    }

    public function test_sales_export_returns_csv_when_requested(): void
    {
        $this->createSaleThroughApi();

        $response = $this->actingAs($this->admin)->get('/api/sales/export?format=csv');

        $response->assertOk();
        $contentType = strtolower((string) $response->headers->get('content-type'));
        $this->assertTrue(
            str_contains($contentType, 'csv') || str_contains($contentType, 'text/plain'),
            'Expected CSV-related content type, got: ' . $contentType,
        );
        $this->assertStringContainsString('Sale ID', $response->streamedContent());
    }

    public function test_sales_export_forbidden_without_export_permission(): void
    {
        $staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff-export@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STAFF,
        ]);

        $this->actingAs($staff)
            ->getJson('/api/sales/export')
            ->assertStatus(403);
    }

    public function test_sales_export_respects_customer_filter(): void
    {
        $this->createSaleThroughApi(['customer_id' => $this->customer->id]);
        $this->createSaleThroughApi(['customer_id' => $this->otherCustomer->id]);

        $response = $this->actingAs($this->admin)->get(
            '/api/sales/export?' . http_build_query(['customer_id' => $this->customer->id, 'format' => 'xlsx'])
        );

        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'sales_export_') . '.xlsx';
        file_put_contents($path, $response->streamedContent());

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertSame(2, (int) $sheet->getHighestDataRow());
        $this->assertSame('Ahmed Saleh', (string) $sheet->getCell('C2')->getValue());
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
            'admin_password' => 'password',
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
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
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
