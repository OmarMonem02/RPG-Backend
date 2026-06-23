<?php

namespace Tests\Feature;

use App\Http\Requests\Concerns\ValidatesSellablePayload;
use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Support\SellerTestFactory;
use Tests\TestCase;

class UnstoredLineItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Seller $seller;

    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '01009998877',
        ]);

        $this->seller = SellerTestFactory::create([
            'name' => 'Seller',
            'phone' => '01112223344',
            'commission_rate' => 5,
        ]);

        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);
    }

    public function test_can_create_sale_with_unstored_item_without_inventory_change(): void
    {
        $category = ProductCategory::create(['name' => 'Cat']);
        $brand = Brand::create(['name' => 'Brand', 'types' => ['products']]);
        $product = Product::create([
            'name' => 'Stock Item',
            'sku' => 'SKU-1',
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'products_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'items' => [
                [
                    'is_unstored' => true,
                    'custom_name' => 'Custom labor',
                    'custom_description' => 'On-site fitting',
                    'unstored_type' => 'maintenance_service',
                    'cost_price' => 50,
                    'selling_price' => 120,
                    'qty' => 2,
                ],
                [
                    'product_id' => $product->id,
                    'selling_price' => 20,
                    'qty' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('sale_items', [
            'custom_name' => 'Custom labor',
            'is_unstored' => true,
            'qty' => 2,
        ]);
        $product->refresh();
        $this->assertSame(4, (int) $product->stock_quantity);
    }

    public function test_rejects_incomplete_unstored_sale_item(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'items' => [
                [
                    'is_unstored' => true,
                    'custom_name' => 'Missing fields',
                    'selling_price' => 10,
                    'qty' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_mixing_catalog_reference_with_unstored_flag(): void
    {
        $category = ProductCategory::create(['name' => 'Cat']);
        $brand = Brand::create(['name' => 'Brand', 'types' => ['products']]);
        $product = Product::create([
            'name' => 'Item',
            'sku' => 'SKU-2',
            'stock_quantity' => 3,
            'low_stock_alarm' => 1,
            'products_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'items' => [
                [
                    'is_unstored' => true,
                    'product_id' => $product->id,
                    'custom_name' => 'Bad row',
                    'custom_description' => 'Desc',
                    'unstored_type' => 'product',
                    'cost_price' => 1,
                    'selling_price' => 2,
                    'qty' => 1,
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_add_unstored_item_to_existing_sale(): void
    {
        $sale = Sale::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'pending',
            'total' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/sales/{$sale->id}/items", [
            'is_unstored' => true,
            'custom_name' => 'Extra part',
            'custom_description' => 'Not in catalog',
            'unstored_type' => 'spare_part',
            'cost_price' => 30,
            'selling_price' => 75,
            'qty' => 1,
        ]);

        $response->assertOk();
        $items = collect($response->json('items'));
        $this->assertTrue(
            $items->contains(
                fn (array $row) => ($row['is_unstored'] ?? false) === true
                    && ($row['custom_name'] ?? '') === 'Extra part',
            ),
        );
    }

    public function test_can_filter_sales_with_unstored_items(): void
    {
        $saleWith = Sale::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'pending',
            'total' => 100,
        ]);
        SaleItem::create([
            'sale_id' => $saleWith->id,
            'is_unstored' => true,
            'custom_name' => 'Custom',
            'custom_description' => 'Desc',
            'unstored_type' => 'product',
            'cost_price' => 10,
            'selling_price' => 100,
            'qty' => 1,
        ]);

        $saleWithout = Sale::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'pending',
            'total' => 50,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/sales?has_unstored_items=1');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($saleWith->id, $ids);
        $this->assertNotContains($saleWithout->id, $ids);
    }

    public function test_can_return_unstored_sale_item_without_inventory_error(): void
    {
        $sale = Sale::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'total' => 200,
        ]);

        $item = SaleItem::create([
            'sale_id' => $sale->id,
            'is_unstored' => true,
            'custom_name' => 'Return me',
            'custom_description' => 'Desc',
            'unstored_type' => 'product',
            'cost_price' => 20,
            'selling_price' => 200,
            'qty' => 2,
            'status' => SaleItem::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/sales/{$sale->id}/returns", [
            'sale_item_id' => $item->id,
            'qty' => 1,
        ]);

        $response->assertOk();
        $item->refresh();
        $this->assertSame(1, (int) $item->returned_qty);
    }

    public function test_ticket_unstored_item_validation_accepts_complete_payload(): void
    {
        $payload = [
            'is_unstored' => true,
            'custom_name' => 'Custom gasket',
            'custom_description' => 'Fabricated on site',
            'unstored_type' => 'maintenance_part',
            'cost_price' => 40,
            'price_snapshot' => 90,
            'qty' => 1,
        ];

        $helper = new class {
            use ValidatesSellablePayload;

            public function rules(): array
            {
                return array_merge([
                    'qty' => ['required', 'integer', 'min:1'],
                    'price_snapshot' => ['nullable', 'numeric'],
                ], $this->unstoredFieldRules(requireSalePrice: false));
            }

            public function validatePayload(array $payload): \Illuminate\Validation\Validator
            {
                $validator = Validator::make($payload, $this->rules());
                $this->validateLineItemReference($validator, $payload);

                return $validator;
            }
        };

        $validator = $helper->validatePayload($payload);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->all()));
    }

    public function test_can_add_unstored_item_to_ticket_task(): void
    {
        $bike = $this->createCustomerBike($this->customer->id);

        $ticket = \App\Models\Ticket::create([
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
        ]);

        $task = \App\Models\TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Custom work',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/tickets/{$ticket->id}/tasks/{$task->id}/items",
            [
                'is_unstored' => true,
                'custom_name' => 'Custom gasket',
                'custom_description' => 'Fabricated on site',
                'unstored_type' => 'maintenance_part',
                'cost_price' => 40,
                'price_snapshot' => 90,
                'qty' => 1,
            ],
        );

        $response
            ->assertCreated()
            ->assertJsonPath('is_unstored', true)
            ->assertJsonPath('custom_name', 'Custom gasket')
            ->assertJsonPath('item_name', 'Custom gasket')
            ->assertJsonPath('qty', 1);

        $this->assertDatabaseHas('ticket_items', [
            'task_id' => $task->id,
            'is_unstored' => true,
            'custom_name' => 'Custom gasket',
            'unstored_type' => 'maintenance_part',
            'cost_price' => 40,
            'price_snapshot' => 90,
            'qty' => 1,
        ]);
    }

    public function test_can_export_unstored_ticket_items(): void
    {
        $bike = $this->createCustomerBike($this->customer->id);

        $ticket = \App\Models\Ticket::create([
            'user_id' => $this->admin->id,
            'customer_id' => $this->customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 90,
        ]);

        $task = \App\Models\TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Custom work',
            'status' => 'pending',
            'subtotal' => 90,
        ]);

        \App\Models\TicketItem::create([
            'task_id' => $task->id,
            'ticket_id' => $ticket->id,
            'is_unstored' => true,
            'custom_name' => 'Ticket export item',
            'custom_description' => 'For ticket export',
            'unstored_type' => 'product',
            'cost_price' => 10,
            'price_snapshot' => 90,
            'qty' => 1,
            'subtotal' => 90,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/tickets/export?has_unstored_items=1&format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            (string) $response->headers->get('content-type'),
        );
    }

    public function test_can_export_unstored_sale_items(): void
    {
        $sale = Sale::create([
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'pending',
            'total' => 150,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'is_unstored' => true,
            'custom_name' => 'Export item',
            'custom_description' => 'For export',
            'unstored_type' => 'product',
            'cost_price' => 15,
            'selling_price' => 150,
            'qty' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get('/api/sales/export?has_unstored_items=1&format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            (string) $response->headers->get('content-type'),
        );
    }

    private function createCustomerBike(int $customerId): CustomerBike
    {
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);

        return CustomerBike::create([
            'customer_id' => $customerId,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-UNCAT-'.uniqid(),
        ]);
    }
}
