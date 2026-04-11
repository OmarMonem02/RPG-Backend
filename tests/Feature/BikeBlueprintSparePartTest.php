<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\BikeBlueprintSparePart;
use App\Models\Brand;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BikeBlueprintSparePartTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BikeBlueprint $bikeBlueprint;
    private SparePart $sparePart1;
    private SparePart $sparePart2;
    private Brand $brand;
    private SparePartCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create necessary data
        $this->brand = Brand::create(['name' => 'Test Brand', 'type' => 'spare_parts']);
        $this->category = SparePartCategory::create(['name' => 'Test Category']);

        $this->sparePart1 = SparePart::create([
            'name' => 'Spare Part 1',
            'sku' => 'TEST-SKU-001',
            'brand_id' => $this->brand->id,
            'spare_parts_category_id' => $this->category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'max_discount_type' => 'fixed',
        ]);

        $this->sparePart2 = SparePart::create([
            'name' => 'Spare Part 2',
            'sku' => 'TEST-SKU-002',
            'brand_id' => $this->brand->id,
            'spare_parts_category_id' => $this->category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 75,
            'sale_price' => 150,
            'max_discount_type' => 'percentage',
        ]);

        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'type' => 'bikes']);
        $this->bikeBlueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Test Model',
            'year' => 2024,
        ]);

        // Create admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    /**
     * Test: Assign a single spare part to a bike blueprint
     */
    public function test_assign_single_spare_part(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_id' => $this->sparePart1->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('bike_blueprint_id', $this->bikeBlueprint->id)
            ->assertJsonPath('spare_part_id', $this->sparePart1->id);

        $this->assertDatabaseHas('bike_blueprint_spare_parts', [
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);
    }

    /**
     * Test: Assign multiple spare parts in bulk
     */
    public function test_assign_bulk_spare_parts(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_ids' => [$this->sparePart1->id, $this->sparePart2->id],
            ]);

        $response->assertStatus(201);

        $this->assertIsArray($response->json());

        $this->assertDatabaseHas('bike_blueprint_spare_parts', [
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);

        $this->assertDatabaseHas('bike_blueprint_spare_parts', [
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart2->id,
        ]);
    }

    /**
     * Test: Create a new spare part and assign it to a bike blueprint
     */
    public function test_create_and_assign_spare_part(): void
    {
        $sparePartData = [
            'name' => 'New Spare Part',
            'sku' => 'NEW-SKU-001',
            'spare_parts_category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'stock_quantity' => 10,
            'cost_price' => 50,
            'sale_price' => 100,
            'currency_pricing' => 'EGP',
            'max_discount_type' => 'fixed',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_data' => $sparePartData,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('spare_parts', ['sku' => 'NEW-SKU-001']);

        $createdSparePart = SparePart::where('sku', 'NEW-SKU-001')->first();
        $this->assertDatabaseHas('bike_blueprint_spare_parts', [
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $createdSparePart->id,
        ]);
    }

    /**
     * Test: Prevent duplicate assignment (unique constraint)
     */
    public function test_prevent_duplicate_assignment(): void
    {
        // Assign once
        $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_id' => $this->sparePart1->id,
            ]);

        // Try to assign the same spare part again
        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_id' => $this->sparePart1->id,
            ]);

        // Should fail with a validation error or database error
        $this->assertTrue(
            $response->status() === 422 || $response->status() === 500,
            'Expected 422 or 500 error for duplicate assignment'
        );

        // Verify only one assignment exists
        $count = BikeBlueprintSparePart::where('bike_blueprint_id', $this->bikeBlueprint->id)
            ->where('spare_part_id', $this->sparePart1->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test: Get spare parts for a bike blueprint
     */
    public function test_get_spare_parts_for_blueprint(): void
    {
        // Assign spare parts
        BikeBlueprintSparePart::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);

        BikeBlueprintSparePart::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart2->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts");

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    /**
     * Test: Get spare parts with filtering by category
     */
    public function test_get_spare_parts_filtered_by_category(): void
    {
        $otherCategory = SparePartCategory::create(['name' => 'Other Category']);
        $sparePart3 = SparePart::create([
            'name' => 'Spare Part 3',
            'sku' => 'TEST-SKU-003',
            'brand_id' => $this->brand->id,
            'spare_parts_category_id' => $otherCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'max_discount_type' => 'fixed',
        ]);

        BikeBlueprintSparePart::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);

        BikeBlueprintSparePart::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $sparePart3->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts?category_id={$this->category->id}");

        $response->assertStatus(200);
    }

    /**
     * Test: Remove a spare part from a bike blueprint
     */
    public function test_remove_spare_part_from_blueprint(): void
    {
        $assignment = BikeBlueprintSparePart::create([
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts/{$this->sparePart1->id}");

        $response->assertStatus(204);

        // Verify soft delete occurred
        $this->assertSoftDeleted('bike_blueprint_spare_parts', [
            'id' => $assignment->id,
            'bike_blueprint_id' => $this->bikeBlueprint->id,
            'spare_part_id' => $this->sparePart1->id,
        ]);
    }

    /**
     * Test: Remove non-existent spare part assignment
     */
    public function test_remove_non_existent_assignment(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts/{$this->sparePart1->id}");

        $response->assertStatus(404);
    }

    /**
     * Test: Validation error when no spare part data provided
     */
    public function test_validation_error_no_data(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spare_part_id']);
    }

    /**
     * Test: Validation error with non-existent spare part ID
     */
    public function test_validation_error_non_existent_spare_part(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['spare_part_id']);
    }

    /**
     * Test: Unauthorized access (non-admin)
     */
    public function test_unauthorized_access(): void
    {
        $user = User::create([
            'name' => 'Staff User',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',  // staff user, not admin
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts", [
                'spare_part_id' => $this->sparePart1->id,
            ]);

        // Should return 403 Forbidden since role:admin middleware requires admin role
        $response->assertStatus(403);
    }

    /**
     * Test: Get spare parts with pagination
     */
    public function test_get_spare_parts_pagination(): void
    {
        // Create multiple assignments
        for ($i = 0; $i < 20; $i++) {
            $sparePart = SparePart::create([
                'name' => "Spare Part {$i}",
                'sku' => "TEST-SKU-{$i}",
                'brand_id' => $this->brand->id,
                'spare_parts_category_id' => $this->category->id,
                'currency_pricing' => 'EGP',
                'cost_price' => 50 + $i,
                'sale_price' => 100 + ($i * 2),
                'max_discount_type' => 'fixed',
            ]);

            BikeBlueprintSparePart::create([
                'bike_blueprint_id' => $this->bikeBlueprint->id,
                'spare_part_id' => $sparePart->id,
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson("/api/bike_blueprints/{$this->bikeBlueprint->id}/spare_parts?per_page=10");

        $response->assertStatus(200)
            ->assertJsonPath('per_page', 10)
            ->assertJsonPath('total', 20); // 20 created in the loop
    }
}
