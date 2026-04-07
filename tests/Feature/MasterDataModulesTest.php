<?php

namespace Tests\Feature;

use App\Models\Bike;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]));
    }

    public function test_it_creates_a_category(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'Engine Parts',
            'type' => Category::TYPE_PART,
            'description' => 'Engine-related stock.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Engine Parts')
            ->assertJsonPath('data.type', Category::TYPE_PART);

        $this->assertDatabaseHas('categories', [
            'name' => 'Engine Parts',
            'type' => Category::TYPE_PART,
        ]);
    }

    public function test_it_prevents_duplicate_category_names_per_type(): void
    {
        Category::query()->create([
            'name' => 'Oil',
            'type' => Category::TYPE_PART,
        ]);

        $response = $this->postJson('/api/categories', [
            'name' => 'Oil',
            'type' => Category::TYPE_PART,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_it_creates_a_bike_blueprint(): void
    {
        $response = $this->postJson('/api/bikes', [
            'brand' => 'Honda',
            'model' => 'CBR 600',
            'year' => 2024,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.brand', 'Honda')
            ->assertJsonPath('data.model', 'CBR 600')
            ->assertJsonPath('data.year', 2024);

        $this->assertDatabaseHas('bikes', [
            'brand' => 'Honda',
            'model' => 'CBR 600',
            'year' => 2024,
        ]);
    }

    public function test_it_prevents_duplicate_bike_blueprints(): void
    {
        Bike::query()->create([
            'brand' => 'Yamaha',
            'model' => 'R1',
            'year' => 2023,
        ]);

        $response = $this->postJson('/api/bikes', [
            'brand' => 'Yamaha',
            'model' => 'R1',
            'year' => 2023,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_it_assigns_a_product_to_bikes_without_duplicate_pivot_rows(): void
    {
        $brand = Brand::query()->create(['name' => 'Motul']);
        $category = Category::query()->create([
            'name' => 'Lubricants',
            'type' => Category::TYPE_PART,
        ]);
        $bike = Bike::query()->create([
            'brand' => 'Suzuki',
            'model' => 'GSX-R750',
            'year' => 2022,
        ]);
        $product = Product::query()->create([
            'type' => Product::TYPE_PART,
            'name' => 'Chain Lube',
            'sku' => 'CHAIN-LUBE-001',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'qty' => 10,
            'cost_price' => 100,
            'selling_price' => 150,
            'max_discount_type' => Product::DISCOUNT_TYPE_FIXED,
            'max_discount_value' => 25,
            'is_universal' => false,
        ]);

        $response = $this->postJson("/api/products/{$product->id}/bikes", [
            'bike_ids' => [$bike->id, $bike->id],
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data.bikes');

        $this->assertDatabaseCount('bike_product', 1);
        $this->assertDatabaseHas('bike_product', [
            'bike_id' => $bike->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_it_creates_a_customer_with_bikes(): void
    {
        $response = $this->postJson('/api/customers', [
            'name' => 'Ahmed Rider',
            'phone' => '01000000001',
            'address' => 'Nasr City',
            'notes' => 'VIP customer',
            'bikes' => [
                [
                    'brand' => 'BMW',
                    'model' => 'S1000RR',
                    'year' => 2021,
                    'modifications' => 'Exhaust upgrade',
                    'notes' => 'Track bike',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ahmed Rider')
            ->assertJsonCount(1, 'data.customer_bikes');

        $this->assertDatabaseHas('customers', [
            'phone' => '01000000001',
        ]);
        $this->assertDatabaseHas('customer_bikes', [
            'brand' => 'BMW',
            'model' => 'S1000RR',
            'year' => 2021,
        ]);
    }

    public function test_it_creates_bike_inventory_records(): void
    {
        $bike = Bike::query()->create([
            'brand' => 'Ducati',
            'model' => 'Panigale V4',
            'year' => 2020,
        ]);

        $response = $this->postJson('/api/bike-inventory', [
            'bike_id' => $bike->id,
            'type' => 'consignment',
            'cost_price' => 350000,
            'selling_price' => 390000,
            'mileage' => 12000,
            'cc' => 1103,
            'horse_power' => 214,
            'owner_name' => 'Mostafa',
            'owner_phone' => '01111111111',
            'notes' => 'Clean condition',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'consignment')
            ->assertJsonPath('data.bike.id', $bike->id);

        $this->assertDatabaseHas('bikes_inventory', [
            'bike_id' => $bike->id,
            'type' => 'consignment',
            'owner_name' => 'Mostafa',
        ]);
    }

    public function test_it_creates_a_service(): void
    {
        $category = Category::query()->create([
            'name' => 'Workshop Services',
            'type' => Category::TYPE_SERVICE,
        ]);

        $response = $this->postJson('/api/services', [
            'category_id' => $category->id,
            'name' => 'Engine Diagnostics',
            'price' => 800,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 10,
            'description' => 'Full diagnostics service',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Engine Diagnostics')
            ->assertJsonPath('data.category.id', $category->id);

        $this->assertDatabaseHas('services', [
            'name' => 'Engine Diagnostics',
            'category_id' => $category->id,
        ]);
    }
}
