<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseInsensitiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.case.search@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_product_search_is_case_insensitive(): void
    {
        $brand = Brand::create(['name' => 'Gear Co', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Accessories']);
        $product = Product::create([
            'name' => 'Racing Helmet',
            'sku' => 'HLM-001',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/products?search=helmet');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($product->id, $ids);
    }

    public function test_spare_part_search_is_case_insensitive(): void
    {
        $brand = Brand::create(['name' => 'Parts Co', 'types' => ['spare_parts']]);
        $category = SparePartCategory::create(['name' => 'Brakes']);
        $part = SparePart::create([
            'name' => 'Ceramic Brake Pad',
            'sku' => 'BRK-001',
            'brand_id' => $brand->id,
            'spare_parts_category_id' => $category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/spare_parts?search=brake');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($part->id, $ids);
    }

    public function test_bike_search_is_case_insensitive_by_model(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);
        $bike = BikeForSale::create([
            'bike_blueprint_id' => $blueprint->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 4000,
            'sale_price' => 5000,
            'status' => 'available',
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'vin' => 'VIN-MT07-001',
            'mileage' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_for_sale?search=mt-07');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($bike->id, $ids);
    }

    public function test_brand_search_is_case_insensitive(): void
    {
        $brand = Brand::create(['name' => 'Honda', 'types' => ['bikes']]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/brands?search=honda');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($brand->id, $ids);
    }
}
