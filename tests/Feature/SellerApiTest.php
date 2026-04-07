<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]));
    }

    public function test_it_creates_and_updates_seller(): void
    {
        $createResponse = $this->postJson('/api/sellers', [
            'name' => 'Ahmed Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 5,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.name', 'Ahmed Seller')
            ->assertJsonPath('data.status', Seller::STATUS_ACTIVE);

        $sellerId = (int) $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/sellers/{$sellerId}", [
            'commission_value' => 7.5,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.commission_value', 7.5);
    }

    public function test_it_lists_sellers_with_metrics(): void
    {
        $seller = Seller::query()->create([
            'name' => 'Metrics Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 10,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        Sale::query()->create([
            'seller_id' => $seller->id,
            'total' => 1000,
            'discount' => 100,
            'status' => Sale::STATUS_COMPLETED,
            'type' => Sale::TYPE_GARAGE,
        ]);

        $response = $this->getJson('/api/sellers?search=Metrics');

        $response->assertOk()
            ->assertJsonPath('data.items.0.id', $seller->id)
            ->assertJsonPath('data.items.0.total_sales_count', 1)
            ->assertJsonPath('data.items.0.total_revenue', 900)
            ->assertJsonPath('data.items.0.average_sale_value', 900);
    }

    public function test_it_returns_seller_sales_history(): void
    {
        $seller = Seller::query()->create([
            'name' => 'History Seller',
            'commission_type' => Seller::COMMISSION_TYPE_PROFIT,
            'commission_value' => 12,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        Sale::query()->create([
            'seller_id' => $seller->id,
            'total' => 500,
            'discount' => 50,
            'status' => Sale::STATUS_PENDING,
            'type' => Sale::TYPE_ONLINE,
        ]);

        $response = $this->getJson("/api/sellers/{$seller->id}/sales?status=".Sale::STATUS_PENDING);

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.seller_id', $seller->id);
    }

    public function test_it_blocks_sale_creation_for_inactive_seller(): void
    {
        $seller = Seller::query()->create([
            'name' => 'Inactive Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 5,
            'status' => Seller::STATUS_INACTIVE,
        ]);

        $response = $this->postJson('/api/sales', [
            'customer' => [
                'name' => 'Sale Customer',
            ],
            'seller_id' => $seller->id,
            'type' => Sale::TYPE_GARAGE,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['seller_id']);
    }

    public function test_it_allows_sale_creation_without_seller(): void
    {
        $response = $this->postJson('/api/sales', [
            'customer' => [
                'name' => 'Walk-in Customer',
            ],
            'type' => Sale::TYPE_GARAGE,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.seller_id', null)
            ->assertJsonPath('data.seller_commission', 0);
    }

    public function test_it_filters_direct_and_seller_based_sales(): void
    {
        $seller = Seller::query()->create([
            'name' => 'Filter Seller',
            'commission_type' => Seller::COMMISSION_TYPE_TOTAL,
            'commission_value' => 5,
            'status' => Seller::STATUS_ACTIVE,
        ]);

        Sale::query()->create([
            'seller_id' => $seller->id,
            'total' => 100,
            'discount' => 0,
            'status' => Sale::STATUS_PENDING,
            'type' => Sale::TYPE_GARAGE,
        ]);

        Sale::query()->create([
            'seller_id' => null,
            'total' => 200,
            'discount' => 0,
            'status' => Sale::STATUS_PENDING,
            'type' => Sale::TYPE_GARAGE,
        ]);

        $sellerBased = $this->getJson('/api/sales?sale_source=seller_based');
        $direct = $this->getJson('/api/sales?sale_source=direct');

        $sellerBased->assertOk()->assertJsonPath('data.pagination.total', 1);
        $direct->assertOk()->assertJsonPath('data.pagination.total', 1);
    }
}
