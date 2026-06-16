<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\InventoryImage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryImagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_product_create_with_images_exposes_primary_via_accessor(): void
    {
        $categoryId = ProductCategory::query()->create(['name' => 'Cat'])->id;
        $brandId = Brand::query()->create(['name' => 'Brand', 'types' => ['products']])->id;

        $response = $this->actingAs($this->admin)->postJson('/api/products', [
            'name' => 'Multi Image Product',
            'sku' => 'IMG-PROD-1',
            'products_category_id' => $categoryId,
            'brand_id' => $brandId,
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'universal' => true,
            'images' => [
                [
                    'url' => 'https://example.com/primary.jpg',
                    'public_id' => 'rpg-system/products/primary',
                    'is_primary' => true,
                    'sort_order' => 0,
                ],
                [
                    'url' => 'https://example.com/secondary.jpg',
                    'public_id' => 'rpg-system/products/secondary',
                    'is_primary' => false,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('image', 'https://example.com/primary.jpg');
        $response->assertJsonPath('image_public_id', 'rpg-system/products/primary');
        $response->assertJsonCount(2, 'images');

        $productId = $response->json('id');
        $this->assertDatabaseHas('inventory_images', [
            'imageable_type' => Product::class,
            'imageable_id' => $productId,
            'url' => 'https://example.com/primary.jpg',
            'is_primary' => true,
        ]);
    }

    public function test_spare_part_update_rejects_more_than_four_images(): void
    {
        $category = SparePartCategory::query()->create(['name' => 'Parts']);
        $brand = Brand::query()->create(['name' => 'Parts Brand', 'types' => ['spare_parts']]);
        $sparePart = SparePart::query()->create([
            'name' => 'Part',
            'sku' => 'PART-IMG-1',
            'spare_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'universal' => true,
        ]);

        $images = [];
        for ($i = 0; $i < 5; $i++) {
            $images[] = [
                'url' => "https://example.com/part-{$i}.jpg",
                'public_id' => "rpg-system/spare-parts/part-{$i}",
                'is_primary' => $i === 0,
                'sort_order' => $i,
            ];
        }

        $response = $this->actingAs($this->admin)->putJson("/api/spare_parts/{$sparePart->id}", [
            'name' => 'Part',
            'sku' => 'PART-IMG-1',
            'spare_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'universal' => true,
            'images' => $images,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images']);
    }

    public function test_spare_part_update_requires_exactly_one_primary_image(): void
    {
        $category = SparePartCategory::query()->create(['name' => 'Parts']);
        $brand = Brand::query()->create(['name' => 'Parts Brand', 'types' => ['spare_parts']]);
        $sparePart = SparePart::query()->create([
            'name' => 'Part',
            'sku' => 'PART-IMG-2',
            'spare_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/spare_parts/{$sparePart->id}", [
            'name' => 'Part',
            'sku' => 'PART-IMG-2',
            'spare_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'universal' => true,
            'images' => [
                [
                    'url' => 'https://example.com/a.jpg',
                    'is_primary' => true,
                    'sort_order' => 0,
                ],
                [
                    'url' => 'https://example.com/b.jpg',
                    'is_primary' => true,
                    'sort_order' => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['images']);
    }

    public function test_bike_for_sale_show_includes_images(): void
    {
        $bikeBrand = Brand::query()->create(['name' => 'Bike Brand', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::query()->create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Test',
            'year' => 2024,
        ]);

        $bike = BikeForSale::query()->create([
            'bike_blueprint_id' => $blueprint->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 1000,
            'sale_price' => 1500,
            'status' => 'available',
            'max_discount_type' => 'percentage',
            'max_discount_value' => 0,
            'vin' => 'VIN-IMG-TEST-1',
            'mileage' => 0,
        ]);

        InventoryImage::query()->create([
            'imageable_type' => BikeForSale::class,
            'imageable_id' => $bike->id,
            'url' => 'https://example.com/bike-primary.jpg',
            'public_id' => 'rpg-system/bikes/primary',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->admin)->getJson("/api/bike_for_sale/{$bike->id}");

        $response->assertOk();
        $response->assertJsonPath('image', 'https://example.com/bike-primary.jpg');
        $response->assertJsonCount(1, 'images');
        $response->assertJsonPath('images.0.is_primary', true);
    }
}
