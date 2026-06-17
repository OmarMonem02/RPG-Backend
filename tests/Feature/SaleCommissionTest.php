<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\SellerTestFactory;
use Tests\TestCase;

class SaleCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Seller $seller;

    private PaymentMethod $paymentMethod;

    private Product $product;

    private SparePart $sparePart;

    private MaintenanceService $maintenanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->customer = Customer::query()->create([
            'name' => 'Commission Customer',
            'phone' => '01055556666',
        ]);
        $this->seller = SellerTestFactory::create([
            'name' => 'Commission Seller',
            'phone' => '01155556666',
            'products_commission_rate' => 10,
            'spare_parts_commission_rate' => 5,
            'maintenance_services_commission_rate' => 20,
        ]);
        $this->paymentMethod = PaymentMethod::query()->create(['name' => 'Cash']);

        $productBrand = Brand::query()->create(['name' => 'Product Brand', 'types' => ['products']]);
        $productCategory = ProductCategory::query()->create(['name' => 'Products']);
        $this->product = Product::query()->create([
            'name' => 'Commission Product',
            'sku' => 'COMM-PROD',
            'brand_id' => $productBrand->id,
            'products_category_id' => $productCategory->id,
            'stock_quantity' => 10,
            'low_stock_alarm' => 1,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 100,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'have_commission' => true,
        ]);

        $spareBrand = Brand::query()->create(['name' => 'Spare Brand', 'types' => ['spare_parts']]);
        $spareCategory = SparePartCategory::query()->create(['name' => 'Spares']);
        $this->sparePart = SparePart::query()->create([
            'name' => 'Commission Spare',
            'sku' => 'COMM-SPARE',
            'brand_id' => $spareBrand->id,
            'spare_parts_category_id' => $spareCategory->id,
            'stock_quantity' => 10,
            'low_stock_alarm' => 1,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 20,
            'sale_price' => 200,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'have_commission' => true,
        ]);

        $sector = MaintenanceServiceSector::query()->create(['name' => 'Engine']);
        $this->maintenanceService = MaintenanceService::query()->create([
            'name' => 'Commission Service',
            'sale_currency' => 'EGP',
            'service_price' => 50,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'maintenance_service_sector_id' => $sector->id,
            'have_commission' => true,
        ]);
    }

    public function test_completed_sale_show_includes_per_type_commission_totals(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/sales', [
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
                    'selling_price' => 100,
                    'discount' => 0,
                    'qty' => 1,
                ],
                [
                    'spare_part_id' => $this->sparePart->id,
                    'selling_price' => 200,
                    'discount' => 0,
                    'qty' => 1,
                ],
                [
                    'maintenance_service_id' => $this->maintenanceService->id,
                    'selling_price' => 50,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ]);

        $response->assertCreated();
        $saleId = (int) $response->json('id');

        $show = $this->actingAs($this->admin)->getJson("/api/sales/{$saleId}");
        $show->assertOk()
            ->assertJsonPath('commission_base', 350)
            ->assertJsonPath('commission_amount', 30);

        $items = collect($show->json('items'));
        $this->assertSame(10.0, (float) $items->firstWhere('product_id', $this->product->id)['commission_amount']);
        $this->assertSame(10.0, (float) $items->firstWhere('spare_part_id', $this->sparePart->id)['commission_amount']);
        $this->assertSame(10.0, (float) $items->firstWhere('maintenance_service_id', $this->maintenanceService->id)['commission_amount']);
    }

    public function test_pending_sale_returns_zero_commission(): void
    {
        $this->actingAs($this->admin)->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'shipping_fee' => 0,
            'discount' => 0,
            'admin_password' => 'password',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 100,
                    'discount' => 0,
                    'qty' => 1,
                ],
            ],
        ])->assertCreated();

        $saleId = (int) Sale::query()->latest('id')->value('id');
        Sale::query()->whereKey($saleId)->update(['status' => Sale::STATUS_PENDING]);

        $show = $this->actingAs($this->admin)->getJson("/api/sales/{$saleId}");

        $show->assertOk()
            ->assertJsonPath('commission_base', 0)
            ->assertJsonPath('commission_amount', 0);
    }
}
