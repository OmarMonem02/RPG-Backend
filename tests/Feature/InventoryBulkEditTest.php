<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryBulkEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Brand $brand;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->brand = Brand::create(['name' => 'Bulk Brand', 'types' => ['products']]);
        $this->category = ProductCategory::create(['name' => 'Bulk Cat']);
    }

    public function test_preview_returns_new_field_changes(): void
    {
        $product = $this->createProduct([
            'item_status' => 'used',
            'have_commission' => false,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
            'stock_quantity' => 2,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/products/bulk/preview', [
            'ids' => [$product->id],
            'changes' => [
                'stock_quantity' => ['mode' => 'set', 'value' => 10],
                'item_status' => ['mode' => 'set', 'value' => 'new'],
                'have_commission' => ['mode' => 'set', 'value' => true],
                'max_discount_type' => ['mode' => 'set', 'value' => 'percentage'],
                'max_discount_value' => ['mode' => 'set', 'value' => 15],
            ],
        ]);

        $response->assertOk();
        $row = $response->json('rows.0');
        $this->assertSame($product->id, $row['id']);
        $this->assertContains('stock_quantity', $row['changed_fields']);
        $this->assertContains('item_status', $row['changed_fields']);
        $this->assertContains('have_commission', $row['changed_fields']);
        $this->assertContains('max_discount_type', $row['changed_fields']);
        $this->assertContains('max_discount_value', $row['changed_fields']);
        $this->assertSame(2, $row['before']['stock_quantity']);
        $this->assertSame(10, $row['after']['stock_quantity']);
        $this->assertSame('used', $row['before']['item_status']);
        $this->assertSame('new', $row['after']['item_status']);
    }

    public function test_apply_updates_scalar_fields(): void
    {
        $product = $this->createProduct([
            'item_status' => 'used',
            'have_commission' => false,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
        ]);

        $response = $this->actingAs($this->admin)->patchJson('/api/products/bulk/apply', [
            'ids' => [$product->id],
            'changes' => [
                'item_status' => ['mode' => 'set', 'value' => 'new'],
                'have_commission' => ['mode' => 'set', 'value' => true],
                'max_discount_type' => ['mode' => 'set', 'value' => 'percentage'],
                'max_discount_value' => ['mode' => 'set', 'value' => 20],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('updated', 1);

        $product->refresh();
        $this->assertSame('new', $product->item_status instanceof \BackedEnum ? $product->item_status->value : $product->item_status);
        $this->assertTrue($product->have_commission);
        $this->assertSame('percentage', $product->max_discount_type);
        $this->assertEquals(20.0, (float) $product->max_discount_value);
    }

    public function test_apply_sets_universal_and_clears_blueprints(): void
    {
        $bikeBrand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'R1',
            'year' => 2024,
        ]);

        $product = $this->createProduct(['universal' => false]);
        $product->bikeBlueprints()->sync([$blueprint->id]);

        $response = $this->actingAs($this->admin)->patchJson('/api/products/bulk/apply', [
            'ids' => [$product->id],
            'changes' => [
                'universal' => ['mode' => 'set', 'value' => true],
            ],
        ]);

        $response->assertOk();
        $product->refresh();
        $this->assertTrue($product->universal);
        $this->assertCount(0, $product->bikeBlueprints);
    }

    public function test_apply_sets_specific_compatibility_with_blueprints(): void
    {
        $bikeBrand = Brand::create(['name' => 'Honda', 'types' => ['bikes']]);
        $blueprintA = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'CBR600',
            'year' => 2020,
        ]);
        $blueprintB = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'CBR1000',
            'year' => 2022,
        ]);

        $product = $this->createProduct(['universal' => true]);

        $response = $this->actingAs($this->admin)->patchJson('/api/products/bulk/apply', [
            'ids' => [$product->id],
            'changes' => [
                'universal' => ['mode' => 'set', 'value' => false],
                'bike_blueprint_ids' => ['mode' => 'set', 'value' => [$blueprintA->id, $blueprintB->id]],
            ],
        ]);

        $response->assertOk();
        $product->refresh();
        $this->assertFalse($product->universal);
        $this->assertEqualsCanonicalizing(
            [$blueprintA->id, $blueprintB->id],
            $product->bikeBlueprints->pluck('id')->all(),
        );
    }

    public function test_preview_includes_compatibility_labels(): void
    {
        $bikeBrand = Brand::create(['name' => 'Kawasaki', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Ninja',
            'year' => 2021,
        ]);

        $product = $this->createProduct(['universal' => true]);

        $response = $this->actingAs($this->admin)->postJson('/api/products/bulk/preview', [
            'ids' => [$product->id],
            'changes' => [
                'universal' => ['mode' => 'set', 'value' => false],
                'bike_blueprint_ids' => ['mode' => 'set', 'value' => [$blueprint->id]],
            ],
        ]);

        $response->assertOk();
        $row = $response->json('rows.0');
        $this->assertContains('compatibility', $row['changed_fields']);
        $this->assertTrue($row['before']['universal']);
        $this->assertFalse($row['after']['universal']);
        $this->assertSame(['Ninja · 2021'], $row['after']['bike_blueprint_labels']);
    }

    public function test_validation_requires_blueprints_when_universal_is_false(): void
    {
        $product = $this->createProduct(['universal' => true]);

        $response = $this->actingAs($this->admin)->postJson('/api/products/bulk/preview', [
            'ids' => [$product->id],
            'changes' => [
                'universal' => ['mode' => 'set', 'value' => false],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['changes.bike_blueprint_ids']);
    }

    public function test_preview_with_filter_only_payload(): void
    {
        $lowStock = $this->createProduct([
            'sku' => 'LOW-001',
            'stock_quantity' => 1,
            'low_stock_alarm' => 5,
            'item_status' => 'new',
        ]);
        $this->createProduct([
            'sku' => 'OK-001',
            'stock_quantity' => 20,
            'low_stock_alarm' => 5,
            'item_status' => 'used',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/products/bulk/preview', [
            'filters' => [
                'item_status' => 'new',
                'low_stock' => true,
            ],
            'changes' => [
                'stock_quantity' => ['mode' => 'set', 'value' => 10],
            ],
        ]);

        $response->assertOk();
        $rows = $response->json('rows');
        $this->assertCount(1, $rows);
        $this->assertSame($lowStock->id, $rows[0]['id']);
    }

    public function test_preview_filters_by_item_status_without_ids(): void
    {
        $used = $this->createProduct(['sku' => 'USED-001', 'item_status' => 'used']);
        $this->createProduct(['sku' => 'NEW-001', 'item_status' => 'new']);

        $response = $this->actingAs($this->admin)->postJson('/api/products/bulk/preview', [
            'filters' => ['item_status' => 'used'],
            'changes' => [
                'have_commission' => ['mode' => 'set', 'value' => false],
            ],
        ]);

        $response->assertOk();
        $ids = collect($response->json('rows'))->pluck('id')->all();
        $this->assertSame([$used->id], $ids);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => 'Bulk Product',
            'sku' => 'BP-'.uniqid(),
            'brand_id' => $this->brand->id,
            'products_category_id' => $this->category->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'stock_quantity' => 5,
            'low_stock_alarm' => 2,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
            'have_commission' => true,
            'item_status' => 'new',
        ], $overrides));
    }
}
