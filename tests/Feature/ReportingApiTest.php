<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_profit_loss_balance_sheet_and_annual_summary_are_calculated_by_currency(): void
    {
        [
            'cash' => $cash,
            'bank' => $bank,
            'customer' => $customer,
            'egpProduct' => $egpProduct,
            'usdProduct' => $usdProduct,
            'egpSparePart' => $egpSparePart,
            'usdService' => $usdService,
            'bike' => $bike,
        ] = $this->seedReportingFixtures();

        $completedEgpSale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'total' => 380,
            'discount' => 20,
            'payment_method_id' => $cash->id,
            'type' => Sale::TYPE_SITE,
            'status' => Sale::STATUS_COMPLETED,
            'shipping_fee' => 0,
            'is_maintenance' => false,
        ]);
        $completedEgpSale->forceFill([
            'created_at' => '2026-03-10 10:00:00',
            'updated_at' => '2026-03-10 10:00:00',
        ])->saveQuietly();

        SaleItem::create([
            'sale_id' => $completedEgpSale->id,
            'product_id' => $egpProduct->id,
            'selling_price' => 200,
            'discount' => 10,
            'qty' => 2,
            'returned_qty' => 0,
            'status' => SaleItem::STATUS_ACTIVE,
        ]);

        $partialUsdSale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'total' => 250,
            'discount' => 0,
            'payment_method_id' => $bank->id,
            'type' => Sale::TYPE_ONLINE,
            'status' => Sale::STATUS_PARTIAL,
            'shipping_fee' => 0,
            'is_maintenance' => true,
        ]);
        $partialUsdSale->forceFill([
            'created_at' => '2026-03-11 10:00:00',
            'updated_at' => '2026-03-11 10:00:00',
        ])->saveQuietly();

        SaleItem::create([
            'sale_id' => $partialUsdSale->id,
            'maintenance_service_id' => $usdService->id,
            'selling_price' => 250,
            'discount' => 0,
            'qty' => 1,
            'returned_qty' => 0,
            'status' => SaleItem::STATUS_ACTIVE,
        ]);

        $completedBikeSale = Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $this->admin->id,
            'total' => 5200,
            'discount' => 0,
            'payment_method_id' => $cash->id,
            'type' => Sale::TYPE_DELIVERY,
            'status' => Sale::STATUS_COMPLETED,
            'shipping_fee' => 200,
            'is_maintenance' => false,
        ]);
        $completedBikeSale->forceFill([
            'created_at' => '2026-05-01 10:00:00',
            'updated_at' => '2026-05-01 10:00:00',
        ])->saveQuietly();

        SaleItem::create([
            'sale_id' => $completedBikeSale->id,
            'bike_for_sale_id' => $bike->id,
            'selling_price' => 5000,
            'discount' => 0,
            'qty' => 1,
            'returned_qty' => 0,
            'status' => SaleItem::STATUS_ACTIVE,
        ]);

        Expense::create([
            'title' => 'March rent',
            'category' => Expense::CATEGORY_RENT,
            'amount' => 120,
            'currency' => 'EGP',
            'payment_status' => Expense::STATUS_PAID,
            'incurred_on' => '2026-03-01',
            'paid_at' => '2026-03-02 12:00:00',
        ]);

        Expense::create([
            'title' => 'Utility bill',
            'category' => Expense::CATEGORY_UTILITIES,
            'amount' => 90,
            'currency' => 'EGP',
            'payment_status' => Expense::STATUS_UNPAID,
            'incurred_on' => '2026-03-15',
            'due_date' => '2026-03-25',
        ]);

        Expense::create([
            'title' => 'USD marketing',
            'category' => Expense::CATEGORY_MARKETING,
            'amount' => 40,
            'currency' => 'USD',
            'payment_status' => Expense::STATUS_PAID,
            'incurred_on' => '2026-03-20',
            'paid_at' => '2026-03-21 12:00:00',
        ]);

        $profitLoss = $this->actingAs($this->admin)
            ->getJson('/api/reporting/profit-loss?date_from=2026-03-01&date_to=2026-05-31')
            ->assertOk();

        $profitLoss
            ->assertJsonPath('currencies.EGP.revenue', 5560)
            ->assertJsonPath('currencies.EGP.cogs', 4160)
            ->assertJsonPath('currencies.EGP.gross_profit', 1400)
            ->assertJsonPath('currencies.EGP.operating_expenses', 210)
            ->assertJsonPath('currencies.EGP.net_profit', 1190)
            ->assertJsonPath('currencies.USD.revenue', 0)
            ->assertJsonPath('currencies.USD.operating_expenses', 40)
            ->assertJsonPath('currencies.USD.net_profit', -40);

        $balanceSheet = $this->actingAs($this->admin)
            ->getJson('/api/reporting/balance-sheet?date_from=2026-03-01&date_to=2026-05-31')
            ->assertOk();

        $balanceSheet
            ->assertJsonPath('currencies.EGP.assets.cash_equivalents.total', 5560)
            ->assertJsonPath('currencies.EGP.assets.accounts_receivable', 0)
            ->assertJsonPath('currencies.EGP.liabilities.unpaid_expenses', 90)
            ->assertJsonPath('currencies.USD.assets.accounts_receivable', 250)
            ->assertJsonPath('currencies.USD.liabilities.unpaid_expenses', 0);

        $annualSummary = $this->actingAs($this->admin)
            ->getJson('/api/reporting/annual-summary?year=2026')
            ->assertOk();

        $annualSummary
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('currencies.EGP.totals.revenue', 5560)
            ->assertJsonPath('currencies.EGP.totals.net_profit', 1190)
            ->assertJsonPath('currencies.EGP.monthly.2.revenue', 360)
            ->assertJsonPath('currencies.EGP.monthly.4.revenue', 5200)
            ->assertJsonPath('currencies.USD.totals.revenue', 0);

        $expensesSummary = $this->actingAs($this->admin)
            ->getJson('/api/reporting/expenses?date_from=2026-03-01&date_to=2026-03-31')
            ->assertOk();

        $expensesSummary
            ->assertJsonPath('summary.EGP.total', 210)
            ->assertJsonPath('summary.USD.total', 40)
            ->assertJsonCount(3, 'data');
    }

    public function test_expense_crud_and_filters_work(): void
    {
        $createResponse = $this->actingAs($this->admin)
            ->postJson('/api/expenses', [
                'title' => 'Fuel reimbursement',
                'category' => Expense::CATEGORY_TRANSPORT,
                'amount' => 75.5,
                'currency' => 'EGP',
                'payment_status' => Expense::STATUS_UNPAID,
                'incurred_on' => '2026-04-07',
                'due_date' => '2026-04-15',
                'notes' => 'Field delivery support',
            ])
            ->assertCreated();

        $expenseId = $createResponse->json('id');

        $this->actingAs($this->admin)
            ->putJson("/api/expenses/{$expenseId}", [
                'payment_status' => Expense::STATUS_PAID,
                'paid_at' => '2026-04-08 09:30:00',
                'notes' => 'Settled in cash',
            ])
            ->assertOk()
            ->assertJsonPath('payment_status', Expense::STATUS_PAID);

        $this->actingAs($this->admin)
            ->getJson('/api/expenses?currency=EGP&payment_status=paid&category=transport')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $expenseId);

        $this->actingAs($this->admin)
            ->deleteJson("/api/expenses/{$expenseId}")
            ->assertNoContent();

        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedReportingFixtures(): array
    {
        $customer = Customer::create([
            'name' => 'Finance Customer',
            'phone' => '01000000000',
        ]);

        $cash = PaymentMethod::create(['name' => 'Cash']);
        $bank = PaymentMethod::create(['name' => 'Bank']);

        $productCategory = ProductCategory::create(['name' => 'Gear']);
        $sparePartCategory = SparePartCategory::create(['name' => 'Parts']);
        $sector = MaintenanceServiceSector::create(['name' => 'Workshop']);

        $productBrand = Brand::create(['name' => 'Product Brand', 'type' => 'products']);
        $sparePartBrand = Brand::create(['name' => 'Spare Brand', 'type' => 'spare_parts']);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'type' => 'bikes']);

        $bikeBlueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Rally 500',
            'year' => 2026,
        ]);

        $egpProduct = Product::create([
            'name' => 'Helmet',
            'sku' => 'REP-001',
            'stock_quantity' => 4,
            'low_stock_alarm' => 1,
            'products_category_id' => $productCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 80,
            'sale_price' => 200,
            'brand_id' => $productBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 10,
        ]);

        $usdProduct = Product::create([
            'name' => 'GPS Unit',
            'sku' => 'REP-002',
            'stock_quantity' => 3,
            'low_stock_alarm' => 1,
            'products_category_id' => $productCategory->id,
            'currency_pricing' => 'USD',
            'cost_price' => 60,
            'sale_price' => 120,
            'brand_id' => $productBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
        ]);

        $egpSparePart = SparePart::create([
            'name' => 'Brake Disc',
            'sku' => 'RES-001',
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $sparePartCategory->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 40,
            'sale_price' => 100,
            'brand_id' => $sparePartBrand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 10,
        ]);

        $usdService = MaintenanceService::create([
            'name' => 'Diagnostics',
            'currency_pricing' => 'USD',
            'service_price' => 250,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        $bike = \App\Models\BikeForSale::create([
            'bike_blueprint_id' => $bikeBlueprint->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 4000,
            'sale_price' => 5000,
            'status' => 'available',
            'max_discount_type' => 'fixed',
            'max_discount_value' => 100,
            'vin' => 'REPORT-BIKE-001',
            'mileage' => 500,
        ]);

        return compact(
            'cash',
            'bank',
            'customer',
            'egpProduct',
            'usdProduct',
            'egpSparePart',
            'usdService',
            'bike'
        );
    }
}
