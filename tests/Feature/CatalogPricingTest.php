<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Setting::query()->updateOrCreate(['key' => 'exchange_rate'], ['value' => '50']);
        Setting::query()->updateOrCreate(['key' => 'exchange_rate_eur'], ['value' => '55']);
    }

    public function test_margin_mode_calculates_sale_price_on_create(): void
    {
        $categoryId = $this->createProductCategory();
        $brandId = $this->createBrand('products');

        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'USD Margin Product',
            'sku' => 'USD-MARGIN-1',
            'products_category_id' => $categoryId,
            'brand_id' => $brandId,
            'cost_price' => 10,
            'cost_currency' => 'USD',
            'sale_currency' => 'EGP',
            'sale_price_mode' => 'margin',
            'sale_margin_type' => 'percentage',
            'sale_margin_value' => 10,
            'sale_price' => 0,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('sale_price', '550.00');
        $response->assertJsonPath('sale_currency', 'EGP');
    }

    public function test_exchange_rate_change_updates_margin_items(): void
    {
        $product = Product::query()->create([
            'name' => 'Rate Test Product',
            'sku' => 'RATE-TEST-1',
            'products_category_id' => $this->createProductCategory(),
            'brand_id' => $this->createBrand('products'),
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'cost_currency' => 'USD',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 550,
            'sale_price_mode' => 'margin',
            'sale_margin_type' => 'percentage',
            'sale_margin_value' => 10,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson('/api/settings', [
            'exchange_rate' => 60,
        ]);

        $response->assertOk();
        $response->assertJsonPath('pricing_impact.margin_items_updated', 1);

        $product->refresh();
        $this->assertSame('660.00', (string) $product->sale_price);
    }

    public function test_manual_item_triggers_pricing_alarm(): void
    {
        Product::query()->create([
            'name' => 'Manual Loss Product',
            'sku' => 'MANUAL-LOSS-1',
            'products_category_id' => $this->createProductCategory(),
            'brand_id' => $this->createBrand('products'),
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'cost_currency' => 'USD',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 550,
            'sale_price_mode' => 'manual',
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $this->actingAs($this->admin)->putJson('/api/settings', [
            'exchange_rate' => 60,
        ])->assertOk();

        $response = $this->actingAs($this->admin)->getJson('/api/inventory/pricing-alarms');
        $response->assertOk();
        $response->assertJsonFragment(['sku' => 'MANUAL-LOSS-1']);
    }

    public function test_margin_mode_requires_foreign_cost_and_egp_sale(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'Invalid Margin Product',
            'sku' => 'INVALID-MARGIN-1',
            'products_category_id' => $this->createProductCategory(),
            'brand_id' => $this->createBrand('products'),
            'cost_price' => 100,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'sale_price_mode' => 'margin',
            'sale_margin_type' => 'percentage',
            'sale_margin_value' => 10,
            'sale_price' => 110,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cost_currency']);
    }

    private function createProductCategory(): int
    {
        return (int) \App\Models\ProductCategory::query()->create(['name' => 'Test Category'])->id;
    }

    private function createBrand(string $type): int
    {
        return (int) \App\Models\Brand::query()->create([
            'name' => 'Brand '.uniqid(),
            'type' => $type,
        ])->id;
    }
}
