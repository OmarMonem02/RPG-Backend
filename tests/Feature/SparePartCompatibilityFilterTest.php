<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SparePartCompatibilityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_spare_parts_by_bike_brand_model_year_and_includes_universal(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.compat@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $spBrand = Brand::create(['name' => 'SP Brand', 'types' => ['spare_parts']]);
        $bikeBrandA = Brand::create(['name' => 'Bike Brand A', 'types' => ['bikes']]);
        $bikeBrandB = Brand::create(['name' => 'Bike Brand B', 'types' => ['bikes']]);

        $category = SparePartCategory::create(['name' => 'Cat A']);
        $otherCategory = SparePartCategory::create(['name' => 'Cat B']);

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

        $linkedToA = SparePart::create([
            'name' => 'Linked To A',
            'sku' => 'LINK-A-001',
            'brand_id' => $spBrand->id,
            'spare_parts_category_id' => $category->id,
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

        $linkedToB = SparePart::create([
            'name' => 'Linked To B',
            'sku' => 'LINK-B-001',
            'brand_id' => $spBrand->id,
            'spare_parts_category_id' => $category->id,
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

        $universalInCategory = SparePart::create([
            'name' => 'Universal In Category',
            'sku' => 'UNI-CAT-001',
            'brand_id' => $spBrand->id,
            'spare_parts_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'stock_quantity' => 10,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        $universalOtherCategory = SparePart::create([
            'name' => 'Universal Other Category',
            'sku' => 'UNI-OTH-001',
            'brand_id' => $spBrand->id,
            'spare_parts_category_id' => $otherCategory->id,
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
            "/api/spare_parts?bike_brand_id={$bikeBrandA->id}&bike_model=ModelX&bike_year=2024&category_id={$category->id}"
        );

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($linkedToA->id, $ids);
        $this->assertContains($universalInCategory->id, $ids);

        // Should not leak in unrelated parts when other filters (category) are present
        $this->assertNotContains($linkedToB->id, $ids);
        $this->assertNotContains($universalOtherCategory->id, $ids);
    }
}

