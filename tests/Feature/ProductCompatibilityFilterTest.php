<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompatibilityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_products_by_bike_brand_model_year_and_includes_universal(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.product.compat@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $bikeBrandA = Brand::create(['name' => 'Bike Brand A', 'types' => ['bikes']]);
        $bikeBrandB = Brand::create(['name' => 'Bike Brand B', 'types' => ['bikes']]);

        $category = ProductCategory::create(['name' => 'Cat A']);
        $otherCategory = ProductCategory::create(['name' => 'Cat B']);

        $bpA_2024 = BikeBlueprint::create([
            'brand_id' => $bikeBrandA->id,
            'model' => 'ModelX',
            'year' => 2024,
        ]);

        $bpB_2024 = BikeBlueprint::create([
            'brand_id' => $bikeBrandB->id,
            'model' => 'ModelY',
            'year' => 2024,
        ]);

        $linkedToA = Product::create([
            'name' => 'Linked To A',
            'sku' => 'PROD-LINK-A-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
        ]);
        $linkedToA->bikeBlueprints()->attach([$bpA_2024->id]);

        $linkedToB = Product::create([
            'name' => 'Linked To B',
            'sku' => 'PROD-LINK-B-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 60,
            'sale_price' => 120,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
        ]);
        $linkedToB->bikeBlueprints()->attach([$bpB_2024->id]);

        $universalInCategory = Product::create([
            'name' => 'Universal In Category',
            'sku' => 'PROD-UNI-CAT-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $universalOtherCategory = Product::create([
            'name' => 'Universal Other Category',
            'sku' => 'PROD-UNI-OTH-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $otherCategory->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 11,
            'sale_price' => 22,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(
            "/api/products?bike_brand_id={$bikeBrandA->id}&bike_model=ModelX&bike_year=2024&category_id={$category->id}"
        );

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($linkedToA->id, $ids);
        $this->assertContains($universalInCategory->id, $ids);
        $this->assertNotContains($linkedToB->id, $ids);
        $this->assertNotContains($universalOtherCategory->id, $ids);
    }

    public function test_create_product_with_bike_blueprint_ids_syncs_pivot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Test Category']);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'CBR',
            'year' => 2024,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Compatible Product',
            'sku' => 'PROD-COMP-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
            'bike_blueprint_ids' => [$blueprint->id],
        ]);

        $response->assertCreated();
        $productId = $response->json('id');

        $this->assertDatabaseHas('bike_blueprint_products', [
            'product_id' => $productId,
            'bike_blueprint_id' => $blueprint->id,
        ]);
    }

    public function test_update_product_syncs_bike_blueprint_ids(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Test Category']);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'types' => ['bikes']]);
        $blueprintA = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Alpha',
            'year' => 2023,
        ]);
        $blueprintB = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Beta',
            'year' => 2024,
        ]);

        $product = Product::create([
            'name' => 'Update Product',
            'sku' => 'PROD-UPD-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
        ]);
        $product->bikeBlueprints()->attach([$blueprintA->id]);

        $response = $this->actingAs($admin)->putJson("/api/products/{$product->id}", [
            'bike_blueprint_ids' => [$blueprintB->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('bike_blueprint_products', [
            'product_id' => $product->id,
            'bike_blueprint_id' => $blueprintA->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('bike_blueprint_products', [
            'product_id' => $product->id,
            'bike_blueprint_id' => $blueprintB->id,
        ]);
    }

    public function test_validation_rejects_non_universal_product_without_blueprints(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $productBrand = Brand::create(['name' => 'Product Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Test Category']);

        $response = $this->actingAs($admin)->postJson('/api/products', [
            'name' => 'Invalid Product',
            'sku' => 'PROD-INVALID-001',
            'brand_id' => $productBrand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
            'bike_blueprint_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bike_blueprint_ids']);
    }
}
