<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SparePartBikeBlueprintIdsTest extends TestCase
{
    use RefreshDatabase;

    public function test_spare_part_show_includes_bike_blueprint_ids_from_pivot(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $spareBrand = Brand::create(['name' => 'Parts Brand', 'types' => ['spare_parts']]);
        $category = SparePartCategory::create(['name' => 'Test Category']);
        $sparePart = SparePart::create([
            'name' => 'Linked Part',
            'sku' => 'SKU-BP-IDS',
            'brand_id' => $spareBrand->id,
            'spare_parts_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'universal' => false,
        ]);

        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'types' => ['bikes']]);
        $blueprintA = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Zebra',
            'year' => 2022,
        ]);
        $blueprintB = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Alpha',
            'year' => 2023,
        ]);

        $sparePart->bikeBlueprints()->attach([$blueprintA->id, $blueprintB->id]);

        $response = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");

        $response->assertOk()
            ->assertJsonStructure(['bike_blueprint_ids', 'bike_blueprints']);

        $ids = $response->json('bike_blueprint_ids');
        $this->assertEqualsCanonicalizing([$blueprintA->id, $blueprintB->id], $ids);

        // Stable order: model, then year (Alpha 2023 before Zebra 2022)
        $this->assertSame([$blueprintB->id, $blueprintA->id], $ids);

        $nestedIds = collect($response->json('bike_blueprints'))->pluck('id')->all();
        $this->assertSame($ids, $nestedIds);
    }
}
