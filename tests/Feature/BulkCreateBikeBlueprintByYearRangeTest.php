<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkCreateBikeBlueprintByYearRangeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
    }

    public function test_creates_blueprint_for_each_year_in_range(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/bike_blueprints/bulk/create-by-range', [
                'brand_id' => $this->brand->id,
                'model' => 'YZF R1',
                'year_from' => 2000,
                'year_to' => 2002,
            ]);

        $response->assertCreated()
            ->assertJsonPath('count_created', 3)
            ->assertJsonPath('count_restored', 0)
            ->assertJsonPath('count_skipped', 0);

        $this->assertSame(3, BikeBlueprint::count());
        $this->assertDatabaseHas('bike_blueprints', [
            'brand_id' => $this->brand->id,
            'model' => 'YZF R1',
            'year' => 2000,
        ]);
        $this->assertDatabaseHas('bike_blueprints', [
            'brand_id' => $this->brand->id,
            'model' => 'YZF R1',
            'year' => 2002,
        ]);
    }

    public function test_skips_existing_years_and_creates_missing_ones(): void
    {
        BikeBlueprint::create([
            'brand_id' => $this->brand->id,
            'model' => 'YZF R1',
            'year' => 2001,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/bike_blueprints/bulk/create-by-range', [
                'brand_id' => $this->brand->id,
                'model' => 'YZF R1',
                'year_from' => 2000,
                'year_to' => 2002,
            ]);

        $response->assertCreated()
            ->assertJsonPath('count_created', 2)
            ->assertJsonPath('count_skipped', 1);

        $skipped = $response->json('skipped');
        $this->assertSame(2001, $skipped[0]['year']);
        $this->assertSame('already_exists', $skipped[0]['reason']);
        $this->assertSame(3, BikeBlueprint::count());
    }

    public function test_restores_soft_deleted_year_in_range(): void
    {
        $blueprint = BikeBlueprint::create([
            'brand_id' => $this->brand->id,
            'model' => 'YZF R1',
            'year' => 2001,
        ]);
        $blueprint->delete();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/bike_blueprints/bulk/create-by-range', [
                'brand_id' => $this->brand->id,
                'model' => 'YZF R1',
                'year_from' => 2000,
                'year_to' => 2002,
            ]);

        $response->assertCreated()
            ->assertJsonPath('count_created', 2)
            ->assertJsonPath('count_restored', 1)
            ->assertJsonPath('count_skipped', 0);

        $this->assertNull(BikeBlueprint::withTrashed()->find($blueprint->id)?->deleted_at);
        $this->assertSame(3, BikeBlueprint::count());
    }

    public function test_rejects_when_year_from_is_greater_than_year_to(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/bike_blueprints/bulk/create-by-range', [
                'brand_id' => $this->brand->id,
                'model' => 'YZF R1',
                'year_from' => 2020,
                'year_to' => 2010,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year_to']);
        $this->assertSame(0, BikeBlueprint::count());
    }

    public function test_rejects_when_year_span_exceeds_limit(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/bike_blueprints/bulk/create-by-range', [
                'brand_id' => $this->brand->id,
                'model' => 'YZF R1',
                'year_from' => 1900,
                'year_to' => 2100,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['year_to']);
        $this->assertSame(0, BikeBlueprint::count());
    }
}
