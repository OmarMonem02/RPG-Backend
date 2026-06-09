<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketProductItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_add_product_to_ticket_task(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        Sanctum::actingAs($staff);

        $customer = Customer::create(['name' => 'Product Customer', 'phone' => '01001234567']);
        $bike = $this->createCustomerBike($customer->id);

        $ticket = Ticket::create([
            'user_id' => $staff->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
        ]);

        $task = TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Accessories',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $product = $this->createProduct('Riding Gloves', 150);

        $response = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'product_id' => $product->id,
            'price_snapshot' => 150,
            'qty' => 2,
            'discount' => 0,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('product_id', $product->id)
            ->assertJsonPath('item_name', 'Riding Gloves')
            ->assertJsonPath('qty', 2)
            ->assertJsonPath('subtotal', 300);

        $this->assertDatabaseHas('ticket_items', [
            'task_id' => $task->id,
            'product_id' => $product->id,
            'qty' => 2,
        ]);
    }

    public function test_staff_cannot_exceed_catalog_max_discount_on_product_ticket_item(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        Sanctum::actingAs($staff);

        $customer = Customer::create(['name' => 'Discount Customer', 'phone' => '01007654321']);
        $bike = $this->createCustomerBike($customer->id);

        $ticket = Ticket::create([
            'user_id' => $staff->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
        ]);

        $task = TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Accessories',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $product = $this->createProduct('Helmet', 200, 'percentage', 10);

        $item = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'product_id' => $product->id,
            'price_snapshot' => 200,
            'qty' => 1,
            'discount' => 0,
        ])->assertCreated()->json();

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 30,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 20,
        ])->assertOk()->assertJsonPath('discount', 20);
    }

    private function createCustomerBike(int $customerId): CustomerBike
    {
        $brand = Brand::create(['name' => 'Yamaha', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);

        return CustomerBike::create([
            'customer_id' => $customerId,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-PROD-'.uniqid(),
        ]);
    }

    private function createProduct(
        string $name,
        float $salePrice,
        string $maxDiscountType = 'fixed',
        float $maxDiscountValue = 0,
    ): Product {
        $brand = Brand::create(['name' => 'GearCo '.uniqid(), 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Accessories '.uniqid()]);

        return Product::create([
            'name' => $name,
            'sku' => 'SKU-'.uniqid(),
            'brand_id' => $brand->id,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => $salePrice * 0.5,
            'sale_price' => $salePrice,
            'max_discount_type' => $maxDiscountType,
            'max_discount_value' => $maxDiscountValue,
            'universal' => true,
        ]);
    }
}
