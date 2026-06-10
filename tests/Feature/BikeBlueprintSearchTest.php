<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BikeBlueprintSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_index_search_matches_model(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_blueprints?search=MT-07');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($blueprint->id, $ids);
    }

    public function test_index_search_matches_brand_name(): void
    {
        $brand = Brand::create(['name' => 'Honda', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'CBR600RR',
            'year' => 2023,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_blueprints?search=Honda');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($blueprint->id, $ids);
    }

    public function test_index_search_matches_year(): void
    {
        $brand = Brand::create(['name' => 'Kawasaki', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'Ninja 650',
            'year' => 2022,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_blueprints?search=2022');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($blueprint->id, $ids);
    }

    public function test_index_search_matches_combined_terms(): void
    {
        $brand = Brand::create(['name' => 'Honda', 'types' => ['bikes']]);
        $match = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'CBR600RR',
            'year' => 2023,
        ]);
        BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'CBR600RR',
            'year' => 2021,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_blueprints?search=Honda%202023');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$match->id], $ids);
    }

    public function test_index_filters_by_brand_model_and_year_together(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $match = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-09',
            'year' => 2024,
        ]);
        BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/bike_blueprints?brand=Yamaha&model=MT-09&year=2024');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$match->id], $ids);
    }
}
