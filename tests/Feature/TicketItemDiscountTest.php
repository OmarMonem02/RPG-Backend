<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketItemDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_exceed_catalog_max_discount_on_ticket_item(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        Sanctum::actingAs($staff);

        $customer = Customer::create(['name' => 'Test Customer', 'phone' => '01009998877']);
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
            'name' => 'Service',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $sector = MaintenanceServiceSector::create(['name' => 'General']);
        $service = MaintenanceService::create([
            'name' => 'Brake bleed',
            'sale_currency' => 'EGP',
            'service_price' => 200,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 10,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        $item = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'maintenance_service_id' => $service->id,
            'price_snapshot' => 200,
            'qty' => 1,
            'discount' => 0,
        ])->assertCreated()->json();

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 25,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 20,
        ])->assertOk()->assertJsonPath('discount', 20);
    }

    public function test_technician_cannot_apply_line_discount(): void
    {
        $technician = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        Sanctum::actingAs($technician);

        $customer = Customer::create(['name' => 'Tech Customer', 'phone' => '01005556677']);
        $bike = $this->createCustomerBike($customer->id);

        $ticket = Ticket::create([
            'user_id' => $technician->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
        ]);

        $task = TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Service',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $sector = MaintenanceServiceSector::create(['name' => 'General']);
        $service = MaintenanceService::create([
            'name' => 'Chain lube',
            'sale_currency' => 'EGP',
            'service_price' => 50,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 10,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        $item = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'maintenance_service_id' => $service->id,
            'price_snapshot' => 50,
            'qty' => 1,
            'discount' => 0,
        ])->assertCreated()->json();

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_admin_can_apply_discount_above_staff_catalog_cap(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        $customer = Customer::create(['name' => 'Admin Customer', 'phone' => '01001112233']);
        $bike = $this->createCustomerBike($customer->id);

        $ticket = Ticket::create([
            'user_id' => $admin->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => 0,
        ]);

        $task = TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Service',
            'status' => 'pending',
            'subtotal' => 0,
        ]);

        $sector = MaintenanceServiceSector::create(['name' => 'General']);
        $service = MaintenanceService::create([
            'name' => 'Tune up',
            'sale_currency' => 'EGP',
            'service_price' => 100,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 5,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        $item = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'maintenance_service_id' => $service->id,
            'price_snapshot' => 100,
            'qty' => 1,
            'discount' => 0,
        ])->assertCreated()->json();

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 40,
        ])->assertOk()->assertJsonPath('discount', 40);
    }

    private function createCustomerBike(int $customerId): CustomerBike
    {
        $brand = Brand::create(['name' => 'Honda', 'types' => ['bikes']]);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'CBR',
            'year' => 2024,
        ]);

        return CustomerBike::create([
            'customer_id' => $customerId,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-DISC-'.uniqid(),
        ]);
    }
}
