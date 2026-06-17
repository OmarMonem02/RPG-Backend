<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryColumnFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_filters_products_by_item_status_size_and_universal(): void
    {
        $brand = Brand::create(['name' => 'Filter Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Filter Cat']);

        $match = Product::create([
            'name' => 'Filtered Product',
            'sku' => 'FP-001',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 5,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'item_status' => 'new',
            'size' => 'Large',
            'color' => 'Black',
        ]);

        Product::create([
            'name' => 'Other Product',
            'sku' => 'FP-002',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 5,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
            'item_status' => 'used',
            'size' => 'Small',
            'color' => 'White',
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/api/products?item_status=new&size=Large&universal=true'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_filters_spare_parts_by_price_range(): void
    {
        $brand = Brand::create(['name' => 'Spare Brand', 'types' => ['spare_parts']]);
        $category = SparePartCategory::create(['name' => 'Spare Cat']);

        $cheap = SparePart::create([
            'name' => 'Cheap Part',
            'sku' => 'SP-CHEAP',
            'brand_id' => $brand->id,
            'spare_parts_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 50,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        SparePart::create([
            'name' => 'Expensive Part',
            'sku' => 'SP-EXP',
            'brand_id' => $brand->id,
            'spare_parts_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 500,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/spare_parts?price_range=0:100');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($cheap->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_filters_products_by_stock_range_and_cost_price(): void
    {
        $brand = Brand::create(['name' => 'Profit Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Profit Cat']);

        $match = Product::create([
            'name' => 'High Margin',
            'sku' => 'HP-001',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'stock_quantity' => 15,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        Product::create([
            'name' => 'Low Margin',
            'sku' => 'LP-001',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 90,
            'sale_price' => 100,
            'stock_quantity' => 3,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            '/api/products?stock_min=10&cost_price_range=50:150'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($match->id, $ids);
        $this->assertCount(1, $ids);
    }
}
