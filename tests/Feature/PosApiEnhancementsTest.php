<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosApiEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]));
    }

    public function test_it_returns_inventory_focused_product_listing_fields_and_filters(): void
    {
        $brand = Brand::query()->create(['name' => 'Motul']);
        $category = Category::query()->create([
            'name' => 'Oils',
            'type' => Category::TYPE_PART,
        ]);

        $sellableProduct = Product::query()->create([
            'type' => Product::TYPE_PART,
            'name' => 'Chain Cleaner',
            'sku' => 'CHAIN-001',
            'part_number' => 'CC-001',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'qty' => 3,
            'cost_price' => 100,
            'selling_price' => 150,
            'max_discount_type' => Product::DISCOUNT_TYPE_FIXED,
            'max_discount_value' => 20,
            'is_universal' => true,
        ]);

        ProductUnit::query()->create([
            'product_id' => $sellableProduct->id,
            'unit_name' => 'Box',
            'conversion_factor' => 6,
            'price' => 840,
        ]);

        Product::query()->create([
            'type' => Product::TYPE_PART,
            'name' => 'Out of Stock Cleaner',
            'sku' => 'CHAIN-002',
            'part_number' => 'CC-002',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'qty' => 0,
            'cost_price' => 100,
            'selling_price' => 150,
            'max_discount_type' => Product::DISCOUNT_TYPE_FIXED,
            'max_discount_value' => 20,
            'is_universal' => false,
        ]);

        $response = $this->getJson('/api/products?search=CHAIN&in_stock=1&low_stock=1&has_units=1&sort_by=qty&sort_direction=asc');

        $response->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $sellableProduct->id)
            ->assertJsonPath('data.data.0.stock.in_stock', true)
            ->assertJsonPath('data.data.0.stock.is_low_stock', true)
            ->assertJsonPath('data.data.0.has_units', true)
            ->assertJsonPath('data.data.0.is_sellable', true)
            ->assertJsonPath('data.data.0.pricing.selling_price', 150)
            ->assertJsonPath('data.data.0.discount_policy.max_discount_amount', 20);
    }

    public function test_it_filters_sales_index_for_pos_search_and_payment_status(): void
    {
        $customer = Customer::query()->create([
            'name' => 'Operational Customer',
            'phone' => '01099999999',
        ]);

        $seller = Seller::query()->create([
            'name' => 'POS Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 5,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        $sale = Sale::query()->create([
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'total' => 500,
            'discount' => 50,
            'status' => Sale::STATUS_PARTIAL,
            'type' => Sale::TYPE_GARAGE,
        ]);

        SaleItem::query()->create([
            'sale_id' => $sale->id,
            'item_type' => SaleItem::ITEM_TYPE_PRODUCT,
            'item_id' => 99,
            'item_name' => 'POS Item',
            'price_snapshot' => 500,
            'selling_price_at_time' => 500,
            'cost_price_at_time' => 300,
            'qty' => 1,
            'discount' => 50,
        ]);

        Payment::query()->create([
            'sale_id' => $sale->id,
            'amount' => 200,
            'method' => Payment::METHOD_CASH,
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $response = $this->getJson('/api/sales?search=01099999999&payment_status=partial');

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $sale->id)
            ->assertJsonPath('data.items.0.item_count', 1)
            ->assertJsonPath('data.items.0.payment_status', 'partial')
            ->assertJsonPath('data.items.0.customer_summary.phone', '01099999999')
            ->assertJsonPath('data.items.0.seller_summary.name', 'POS Seller');
    }

    public function test_it_creates_a_sale_with_items_payments_and_completion_in_one_request(): void
    {
        $brand = Brand::query()->create(['name' => 'Castrol']);
        $category = Category::query()->create([
            'name' => 'Lubricants',
            'type' => Category::TYPE_PART,
        ]);

        $product = Product::query()->create([
            'type' => Product::TYPE_PART,
            'name' => 'Engine Oil',
            'sku' => 'OIL-001',
            'part_number' => 'EO-001',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'qty' => 10,
            'cost_price' => 100,
            'selling_price' => 150,
            'max_discount_type' => Product::DISCOUNT_TYPE_FIXED,
            'max_discount_value' => 30,
            'is_universal' => true,
        ]);

        $seller = Seller::query()->create([
            'name' => 'Checkout Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 5,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        $response = $this->postJson('/api/sales', [
            'customer' => [
                'name' => 'Walk-in POS Customer',
                'phone' => '01012312345',
            ],
            'seller_id' => $seller->id,
            'type' => Sale::TYPE_GARAGE,
            'items' => [
                [
                    'item_type' => SaleItem::ITEM_TYPE_PRODUCT,
                    'item_id' => $product->id,
                    'qty' => 2,
                    'discount' => 20,
                ],
            ],
            'payments' => [
                [
                    'amount' => 280,
                    'method' => Payment::METHOD_CASH,
                    'status' => Payment::STATUS_COMPLETED,
                ],
            ],
            'complete_now' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Sale::STATUS_COMPLETED)
            ->assertJsonPath('data.item_count', 1)
            ->assertJsonPath('data.total', 300)
            ->assertJsonPath('data.discount', 20)
            ->assertJsonPath('data.final_amount', 280)
            ->assertJsonPath('data.paid_amount', 280)
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseHas('customers', [
            'phone' => '01012312345',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'item_id' => $product->id,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('payments', [
            'amount' => 280,
            'status' => Payment::STATUS_COMPLETED,
        ]);
        $this->assertDatabaseHas('invoices', [
            'type' => 'sale',
            'status' => 'paid',
        ]);

        $this->assertSame(8, $product->fresh()->qty);
    }

    public function test_one_request_checkout_rolls_back_on_invalid_stock(): void
    {
        $brand = Brand::query()->create(['name' => 'Ipone']);
        $category = Category::query()->create([
            'name' => 'Filters',
            'type' => Category::TYPE_PART,
        ]);

        $product = Product::query()->create([
            'type' => Product::TYPE_PART,
            'name' => 'Oil Filter',
            'sku' => 'FILTER-001',
            'part_number' => 'OF-001',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'qty' => 1,
            'cost_price' => 50,
            'selling_price' => 90,
            'max_discount_type' => Product::DISCOUNT_TYPE_FIXED,
            'max_discount_value' => 10,
            'is_universal' => true,
        ]);

        $response = $this->postJson('/api/sales', [
            'customer' => [
                'name' => 'Rollback Customer',
            ],
            'type' => Sale::TYPE_GARAGE,
            'items' => [
                [
                    'item_type' => SaleItem::ITEM_TYPE_PRODUCT,
                    'item_id' => $product->id,
                    'qty' => 3,
                    'discount' => 0,
                ],
            ],
            'payments' => [
                [
                    'amount' => 270,
                    'method' => Payment::METHOD_CASH,
                    'status' => Payment::STATUS_COMPLETED,
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['qty']);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(1, $product->fresh()->qty);
    }
}
