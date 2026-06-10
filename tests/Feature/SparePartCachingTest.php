<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Seller;
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

    public function test_spare_parts_index_is_not_cached(): void
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
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_spare_part_detail_is_not_cached(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();
        $sparePart = $this->createSparePart($brand->id, $category->id, 'SP-002', 5);

        $firstShow = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");
        $firstShow->assertOk()
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonPath('stock_quantity', 5);

        $this->actingAs($admin)
            ->patchJson("/api/spare_parts/{$sparePart->id}/stock", ['quantity' => 9])
            ->assertOk()
            ->assertJsonPath('stock_quantity', 9);

        $secondShow = $this->actingAs($admin)->getJson("/api/spare_parts/{$sparePart->id}");
        $secondShow->assertOk()
            ->assertHeader('X-Cache-Hit', 'false')
            ->assertJsonPath('stock_quantity', 9);
    }

    public function test_spare_part_list_reflects_stock_after_sale(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();
        $sparePart = $this->createSparePart($brand->id, $category->id, 'SP-SALE', 6);

        $customer = Customer::query()->create([
            'name' => 'Sale Customer',
            'phone' => '01099998888',
        ]);
        $seller = Seller::query()->create([
            'name' => 'Sale Seller',
            'phone' => '01199998888',
            'commission_rate' => 5,
        ]);
        $paymentMethod = PaymentMethod::query()->create(['name' => 'Cash']);

        $this->actingAs($admin)->postJson('/api/sales', [
            'customer_id' => $customer->id,
            'seller_id' => $seller->id,
            'payment_method_id' => $paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => 0,
            'admin_password' => 'password',
            'items' => [
                [
                    'spare_part_id' => $sparePart->id,
                    'selling_price' => 120,
                    'discount' => 0,
                    'qty' => 6,
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('spare_parts', [
            'id' => $sparePart->id,
            'stock_quantity' => 0,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/spare_parts?per_page=20');
        $response->assertOk()
            ->assertHeader('X-Cache-Hit', 'false');

        $items = collect($response->json('data'));
        $listedPart = $items->firstWhere('id', $sparePart->id);
        $this->assertNotNull($listedPart);
        $this->assertSame(0, (int) $listedPart['stock_quantity']);
    }

    /**
     * @return array{0: Brand, 1: SparePartCategory}
     */
    private function createDependencies(): array
    {
        $brand = Brand::query()->create([
            'name' => 'Cache Test Brand',
            'types' => ['bikes'],
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
