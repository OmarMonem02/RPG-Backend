<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SparePartCachingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_spare_parts_index_sets_cache_hit_header_transition(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();
        $this->createSparePart($brand->id, $category->id, 'SP-001');

        $first = $this->actingAs($admin)->getJson('/api/spare_parts?per_page=20');
        $first->assertOk()
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);

        $second = $this->actingAs($admin)->getJson('/api/spare_parts?per_page=20');
        $second->assertOk()
            ->assertHeader('X-Cache-Hit', 'true')
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_spare_part_mutation_invalidates_cached_detail(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();
        $sparePart = $this->createSparePart($brand->id, $category->id, 'SP-002', 5);

        $firstShow = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");
        $firstShow->assertOk()
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonPath('stock_quantity', 5);

        $secondShow = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");
        $secondShow->assertOk()
            ->assertHeader('X-Cache-Hit', 'true')
            ->assertJsonPath('stock_quantity', 5);

        $this->actingAs($admin)
            ->patchJson("/api/spare_parts/{$sparePart->id}/stock", ['quantity' => 9])
            ->assertOk()
            ->assertJsonPath('stock_quantity', 9);

        $thirdShow = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");
        $thirdShow->assertOk()
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonPath('stock_quantity', 9);
    }

    /**
     * @return array{0: Brand, 1: SparePartCategory}
     */
    private function createDependencies(): array
    {
        $brand = Brand::query()->create([
            'name' => 'Cache Test Brand',
            'type' => 'bikes',
        ]);

        $category = SparePartCategory::query()->create([
            'name' => 'Cache Test Category',
        ]);

        return [$brand, $category];
    }

    private function createSparePart(int $brandId, int $categoryId, string $sku, int $stock = 10): SparePart
    {
        return SparePart::query()->create([
            'name' => 'Cache Test Part',
            'sku' => $sku,
            'image' => null,
            'part_number' => "PN-{$sku}",
            'stock_quantity' => $stock,
            'low_stock_alarm' => 3,
            'spare_parts_category_id' => $categoryId,
            'currency_pricing' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 120,
            'brand_id' => $brandId,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 10,
            'universal' => false,
            'notes' => null,
        ]);
    }
}
