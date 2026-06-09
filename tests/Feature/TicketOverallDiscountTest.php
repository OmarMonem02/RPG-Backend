<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\TicketTask;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketOverallDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_apply_overall_ticket_discount(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        [$ticket, $task] = $this->createTicketWithItem($admin->id, subtotal: 200);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('discount', '30.00')
            ->assertJsonPath('total', '170.00');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'discount' => 30,
            'total' => 170,
        ]);
    }

    public function test_staff_cannot_apply_overall_discount_without_approved_request(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);
        Sanctum::actingAs($staff);

        [$ticket] = $this->createTicketWithItem($staff->id, subtotal: 200);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 20,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_approval_request_id']);
    }

    public function test_staff_can_apply_overall_discount_with_approved_request(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        [$ticket] = $this->createTicketWithItem($staff->id, subtotal: 200);

        $requestId = $this->createApprovedTicketDiscountRequest($staff, $ticket, 25, $admin);

        Sanctum::actingAs($staff);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 25,
            'discount_approval_request_id' => $requestId,
        ])
            ->assertOk()
            ->assertJsonPath('discount', '25.00')
            ->assertJsonPath('total', '175.00');

        $this->assertDatabaseHas('approval_requests', [
            'id' => $requestId,
            'status' => ApprovalRequest::STATUS_CONSUMED,
            'consumed_ticket_id' => $ticket->id,
        ]);
    }

    public function test_overall_discount_cannot_exceed_items_subtotal(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        [$ticket] = $this->createTicketWithItem($admin->id, subtotal: 100);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 150,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_closed_ticket_rejects_discount_update(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        [$ticket] = $this->createTicketWithItem($admin->id, subtotal: 200);
        $ticket->update([
            'status' => 'closed',
            'amount_paid' => 200,
            'payment_method' => 'cash',
            'closed_at' => now(),
        ]);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_completed_ticket_allows_discount_update(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        [$ticket] = $this->createTicketWithItem($admin->id, subtotal: 200);
        $ticket->update(['status' => 'completed']);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('discount', '15.00')
            ->assertJsonPath('total', '185.00');
    }

    public function test_line_item_change_caps_overall_discount(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Sanctum::actingAs($admin);

        [$ticket, $task, $item] = $this->createTicketWithItem($admin->id, subtotal: 200);
        $ticket->update(['discount' => 50, 'total' => 150]);

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item->id}", [
            'qty' => 1,
            'discount' => 150,
        ])->assertOk();

        $ticket->refresh();
        $this->assertEquals(50, (float) $ticket->discount);
        $this->assertEquals(0, (float) $ticket->total);
    }

    public function test_technician_cannot_apply_overall_discount(): void
    {
        $technician = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);
        Sanctum::actingAs($technician);

        [$ticket] = $this->createTicketWithItem($technician->id, subtotal: 200);

        $this->patchJson("/api/tickets/{$ticket->id}/discount", [
            'discount' => 10,
        ])->assertForbidden();
    }

    /**
     * @return array{0: Ticket, 1: TicketTask, 2: TicketItem}
     */
    private function createTicketWithItem(int $userId, float $subtotal): array
    {
        $customer = Customer::create(['name' => 'Discount Customer', 'phone' => '01001234567']);
        $bike = $this->createCustomerBike($customer->id);

        $ticket = Ticket::create([
            'user_id' => $userId,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'in_progress',
            'total' => $subtotal,
            'discount' => 0,
        ]);

        $task = TicketTask::create([
            'ticket_id' => $ticket->id,
            'name' => 'Service',
            'status' => 'pending',
            'subtotal' => $subtotal,
        ]);

        $sector = MaintenanceServiceSector::create(['name' => 'General']);
        $service = MaintenanceService::create([
            'name' => 'Inspection',
            'currency_pricing' => 'USD',
            'service_price' => $subtotal,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'maintenance_service_sector_id' => $sector->id,
        ]);

        $item = TicketItem::create([
            'ticket_id' => $ticket->id,
            'task_id' => $task->id,
            'maintenance_service_id' => $service->id,
            'price_snapshot' => $subtotal,
            'discount' => 0,
            'qty' => 1,
            'subtotal' => $subtotal,
        ]);

        return [$ticket, $task, $item];
    }

    private function createApprovedTicketDiscountRequest(
        User $staff,
        Ticket $ticket,
        float $amount,
        User $admin,
    ): int {
        Sanctum::actingAs($staff);

        $createResponse = $this->postJson('/api/approval-requests', [
            'type' => ApprovalRequest::TYPE_TICKET_DISCOUNT,
            'requested_discount_amount' => $amount,
            'discount_input_type' => 'fixed',
            'discount_input_value' => $amount,
            'cart_subtotal' => 200,
            'payload' => [
                'cart_items' => [[
                    'sellable_type' => 'maintenance_services',
                    'sellable_id' => 1,
                    'item_name' => 'Inspection',
                    'selling_price' => 200,
                    'discount_amount' => 0,
                    'quantity' => 1,
                    'currency' => 'USD',
                    'line_total' => 200,
                ]],
                'ticket_context' => [
                    'ticket_id' => $ticket->id,
                    'customer_name' => 'Discount Customer',
                    'discount_scope' => [
                        'maintenance_services' => true,
                    ],
                    'full_cart_subtotal' => 200,
                ],
            ],
        ])->assertCreated();

        $requestId = $createResponse->json('id');

        Sanctum::actingAs($admin);

        $this->postJson("/api/approval-requests/{$requestId}/approve", [
            'approved_discount_amount' => $amount,
            'approved_discount_input_type' => 'fixed',
            'approved_discount_input_value' => $amount,
        ])->assertOk();

        return (int) $requestId;
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
            'vin' => 'VIN-OVERALL-'.uniqid(),
        ]);
    }
}
