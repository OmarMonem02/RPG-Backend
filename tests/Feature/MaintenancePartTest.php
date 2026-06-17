<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\MaintenancePart;
use App\Models\MaintenancePartCategory;
use App\Models\PaymentMethod;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenancePartTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_maintenance_part_with_catalog_attributes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();

        $response = $this->actingAs($admin)->postJson('/api/maintenance_parts', [
            'name' => 'Oil Filter',
            'sku' => 'MP-001',
            'maintenance_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'stock_quantity' => 4,
            'low_stock_alarm' => 1,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 50,
            'sale_price' => 80,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'size' => 'M',
            'color' => 'Black',
            'item_status' => 'used',
            'universal' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('sku', 'MP-001')
            ->assertJsonPath('size', 'M')
            ->assertJsonPath('color', 'Black')
            ->assertJsonPath('item_status', 'used');

        $this->assertDatabaseHas('maintenance_parts', [
            'sku' => 'MP-001',
            'item_status' => 'used',
        ]);
    }

    public function test_staff_without_permission_cannot_list_maintenance_parts(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->getJson('/api/maintenance_parts')->assertForbidden();
    }

    public function test_sale_with_maintenance_part_deducts_stock(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        [$brand, $category] = $this->createDependencies();
        $part = MaintenancePart::query()->create([
            'name' => 'Brake Pad',
            'sku' => 'MP-SALE',
            'maintenance_parts_category_id' => $category->id,
            'brand_id' => $brand->id,
            'stock_quantity' => 5,
            'low_stock_alarm' => 1,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 20,
            'sale_price' => 40,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'item_status' => 'new',
            'universal' => true,
        ]);

        $customer = Customer::query()->create(['name' => 'Buyer', 'phone' => '01011112222']);
        $seller = Seller::query()->create(['name' => 'Seller', 'phone' => '01033334444', 'commission_rate' => 0]);
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
            'items' => [[
                'maintenance_part_id' => $part->id,
                'selling_price' => 40,
                'discount' => 0,
                'qty' => 2,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('maintenance_parts', [
            'id' => $part->id,
            'stock_quantity' => 3,
        ]);
    }

    /**
     * @return array{0: Brand, 1: MaintenancePartCategory}
     */
    private function createDependencies(): array
    {
        $brand = Brand::query()->create([
            'name' => 'Maint Brand',
            'types' => ['maintenance_parts'],
        ]);
        $category = MaintenancePartCategory::query()->create(['name' => 'Filters']);

        return [$brand, $category];
    }
}
