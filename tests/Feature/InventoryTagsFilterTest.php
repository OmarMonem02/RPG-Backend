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

class InventoryTagsFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_filters_products_by_tags_with_and_partial_match(): void
    {
        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Cat A']);

        $tagged = Product::create([
            'name' => 'Tagged Product',
            'sku' => 'PROD-TAG-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => ['Matte Black', 'High Load'],
        ]);

        Product::create([
            'name' => 'Untagged Product',
            'sku' => 'PROD-TAG-002',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => null,
        ]);

        $singleTagResponse = $this->actingAs($this->admin)->getJson('/api/products?tags=Black');
        $singleTagResponse->assertOk();
        $singleTagIds = collect($singleTagResponse->json('data'))->pluck('id')->all();
        $this->assertContains($tagged->id, $singleTagIds);

        $andTagResponse = $this->actingAs($this->admin)->getJson('/api/products?tags=Black,High');
        $andTagResponse->assertOk();
        $andTagIds = collect($andTagResponse->json('data'))->pluck('id')->all();
        $this->assertContains($tagged->id, $andTagIds);

        $noMatchResponse = $this->actingAs($this->admin)->getJson('/api/products?tags=Black,Metal');
        $noMatchResponse->assertOk();
        $noMatchIds = collect($noMatchResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($tagged->id, $noMatchIds);
    }

    public function test_filters_spare_parts_by_tags(): void
    {
        $spareBrand = Brand::create(['name' => 'Spare Brand', 'types' => ['spare_parts']]);
        $category = SparePartCategory::create(['name' => 'Cat A']);

        $tagged = SparePart::create([
            'name' => 'Tagged Spare',
            'sku' => 'SP-TAG-001',
            'brand_id' => $spareBrand->id,
            'spare_parts_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => ['Metallic', 'High Load'],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/spare_parts?tags=Metallic,Load');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($tagged->id, $ids);
    }

    public function test_create_product_with_tags(): void
    {
        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Cat A']);

        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'New Tagged Product',
            'sku' => 'PROD-NEW-TAG-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => ['Black', 'black', ' High Load '],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tags', ['Black', 'High Load']);
    }

    public function test_create_spare_part_with_tags(): void
    {
        $spareBrand = Brand::create(['name' => 'Spare Brand', 'types' => ['spare_parts']]);
        $category = SparePartCategory::create(['name' => 'Cat A']);

        $response = $this->actingAs($this->admin)->postJson('/api/spare_parts', [
            'name' => 'New Tagged Spare',
            'sku' => 'SP-NEW-TAG-001',
            'brand_id' => $spareBrand->id,
            'spare_parts_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => ['Metallic'],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('tags', ['Metallic']);
    }

    public function test_parse_tags_query_param_accepts_comma_string_or_array(): void
    {
        $this->assertSame(['track', 'racing'], Product::parseTagsQueryParam('track,racing'));
        $this->assertSame(['track', 'racing'], Product::parseTagsQueryParam(['track', 'racing']));
        $this->assertNull(Product::parseTagsQueryParam(''));
        $this->assertNull(Product::parseTagsQueryParam(null));
    }

    public function test_search_includes_tags(): void
    {
        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Cat A']);

        $tagged = Product::create([
            'name' => 'Plain Name',
            'sku' => 'PROD-SEARCH-TAG-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'tags' => ['Matte Black'],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/products?search=Black');
        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($tagged->id, $ids);
    }
}
