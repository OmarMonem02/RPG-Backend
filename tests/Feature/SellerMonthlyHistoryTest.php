<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerMonthlyHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Seller $seller;

    private PaymentMethod $paymentMethod;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 12:00:00');

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->customer = Customer::query()->create([
            'name' => 'History Customer',
            'phone' => '01011112222',
        ]);
        $this->seller = Seller::query()->create([
            'name' => 'History Seller',
            'phone' => '01111112222',
            'commission_rate' => 10,
        ]);
        $this->paymentMethod = PaymentMethod::query()->create(['name' => 'Cash']);

        $brand = Brand::query()->create(['name' => 'History Brand', 'types' => ['products']]);
        $category = ProductCategory::query()->create(['name' => 'History Category']);
        $this->product = Product::query()->create([
            'name' => 'History Product',
            'sku' => 'HIST-001',
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'stock_quantity' => 100,
            'low_stock_alarm' => 1,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sellers_index_metrics_are_limited_to_current_month(): void
    {
        $this->createCompletedSale(100, 1, '2026-01-10 10:00:00');
        $this->createCompletedSale(200, 1, '2026-03-05 10:00:00');

        $response = $this->actingAs($this->admin)->getJson('/api/sellers?search=History Seller');
        $response->assertOk()
            ->assertJsonPath('data.0.completed_sales_count', 1)
            ->assertJsonPath('data.0.commission_base', 200)
            ->assertJsonPath('data.0.commission_amount', 20)
            ->assertJsonPath('summary.completed_sales_count', 1)
            ->assertJsonPath('summary.commission_base', 200);
    }

    public function test_monthly_history_returns_twelve_months_for_selected_year(): void
    {
        $this->createCompletedSale(100, 1, '2026-01-10 10:00:00');
        $this->createCompletedSale(250, 2, '2026-03-05 10:00:00');

        $response = $this->actingAs($this->admin)->getJson(
            "/api/sellers/{$this->seller->id}/monthly-history?year=2026"
        );

        $response->assertOk()
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('current_period', '2026-03')
            ->assertJsonPath('seller.id', $this->seller->id)
            ->assertJsonCount(12, 'months')
            ->assertJsonPath('months.0.period', '2026-01')
            ->assertJsonPath('months.0.completed_sales_count', 1)
            ->assertJsonPath('months.0.commission_base', 100)
            ->assertJsonPath('months.0.commission_amount', 10)
            ->assertJsonPath('months.2.period', '2026-03')
            ->assertJsonPath('months.2.completed_sales_count', 1)
            ->assertJsonPath('months.2.commission_base', 500)
            ->assertJsonPath('months.2.commission_amount', 50)
            ->assertJsonPath('months.2.is_current', true)
            ->assertJsonPath('year_totals.completed_sales_count', 2)
            ->assertJsonPath('year_totals.commission_base', 600)
            ->assertJsonPath('year_totals.commission_amount', 60);
    }

    public function test_monthly_history_rejects_invalid_year(): void
    {
        $response = $this->actingAs($this->admin)->getJson(
            "/api/sellers/{$this->seller->id}/monthly-history?year=1999"
        );

        $response->assertStatus(422);
    }

    public function test_monthly_history_requires_sellers_read_permission(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
        ]);

        $response = $this->actingAs($user)->getJson(
            "/api/sellers/{$this->seller->id}/monthly-history"
        );

        $response->assertForbidden();
    }

    public function test_monthly_history_returns_404_for_missing_seller(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/sellers/99999/monthly-history');

        $response->assertNotFound();
    }

    private function createCompletedSale(float $price, int $qty, string $createdAt): void
    {
        $this->actingAs($this->admin)->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => 0,
            'admin_password' => 'password',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => $price,
                    'discount' => 0,
                    'qty' => $qty,
                ],
            ],
        ])->assertCreated();

        Sale::query()
            ->where('seller_id', $this->seller->id)
            ->latest('id')
            ->limit(1)
            ->update(['created_at' => $createdAt, 'updated_at' => $createdAt]);
    }
}
