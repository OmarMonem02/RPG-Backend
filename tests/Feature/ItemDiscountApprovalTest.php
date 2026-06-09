<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Seller;
use App\Models\Ticket;
use App\Models\TicketTask;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemDiscountApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private Customer $customer;
    private Seller $seller;
    private PaymentMethod $paymentMethod;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => UserPermissions::normalizeMatrix([
                'sales' => ['read', 'create', 'update', 'delete'],
                'maintenance' => ['read', 'create', 'update', 'delete'],
            ]),
        ]);

        $this->customer = Customer::create([
            'name' => 'Discount Customer',
            'phone' => '01001234567',
        ]);

        $this->seller = Seller::create([
            'name' => 'Seller',
            'phone' => '01112223333',
            'commission_rate' => 5,
        ]);

        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);

        $brand = Brand::create(['name' => 'Gear', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Accessories']);

        $this->product = Product::create([
            'name' => 'Helmet',
            'sku' => 'PR-DISC-'.uniqid(),
            'stock_quantity' => 10,
            'low_stock_alarm' => 1,
            'products_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'brand_id' => $brand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 20,
        ]);
    }

    public function test_staff_cannot_create_sale_with_over_cap_item_discount_without_approval(): void
    {
        Sanctum::actingAs($this->staff);

        $this->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 30,
                    'qty' => 1,
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_staff_can_create_sale_with_approved_item_discount(): void
    {
        $requestId = $this->createApprovedSaleItemRequest(30);

        Sanctum::actingAs($this->staff);

        $this->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 30,
                    'qty' => 1,
                    'discount_approval_request_id' => $requestId,
                ],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('approval_requests', [
            'id' => $requestId,
            'status' => ApprovalRequest::STATUS_CONSUMED,
        ]);
    }

    public function test_admin_can_apply_over_cap_item_discount_on_sale(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/sales', [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => 0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 50,
                    'qty' => 1,
                ],
            ],
        ])->assertCreated();
    }

    public function test_staff_cannot_update_ticket_item_discount_over_cap_without_approval(): void
    {
        [$ticket, $task, $item] = $this->createTicketItem();

        Sanctum::actingAs($this->staff);

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 30,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount']);
    }

    public function test_staff_can_update_ticket_item_discount_with_approved_request(): void
    {
        [$ticket, $task, $item] = $this->createTicketItem();
        $requestId = $this->createApprovedTicketItemRequest(
            $ticket->id,
            $task->id,
            $item['id'],
            30,
        );

        Sanctum::actingAs($this->staff);

        $this->patchJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items/{$item['id']}", [
            'discount' => 30,
            'discount_approval_request_id' => $requestId,
        ])
            ->assertOk()
            ->assertJsonPath('discount', 30);

        $this->assertDatabaseHas('approval_requests', [
            'id' => $requestId,
            'status' => ApprovalRequest::STATUS_CONSUMED,
        ]);
    }

    private function createApprovedSaleItemRequest(float $discount): int
    {
        $requestId = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->saleItemRequestPayload($discount))
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/approval-requests/{$requestId}/approve", [
                'approved_discount_amount' => $discount,
                'approved_discount_input_type' => 'fixed',
                'approved_discount_input_value' => $discount,
            ])
            ->assertOk();

        return $requestId;
    }

    private function createApprovedTicketItemRequest(
        int $ticketId,
        int $taskId,
        int $ticketItemId,
        float $discount,
    ): int {
        $requestId = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->ticketItemRequestPayload(
                $ticketId,
                $taskId,
                $ticketItemId,
                $discount,
            ))
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/approval-requests/{$requestId}/approve", [
                'approved_discount_amount' => $discount,
                'approved_discount_input_type' => 'fixed',
                'approved_discount_input_value' => $discount,
            ])
            ->assertOk();

        return $requestId;
    }

    private function saleItemRequestPayload(float $discount): array
    {
        return [
            'type' => ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT,
            'requested_discount_amount' => $discount,
            'discount_input_type' => 'fixed',
            'discount_input_value' => $discount,
            'cart_subtotal' => 200,
            'payload' => [
                'cart_items' => [
                    [
                        'sellable_type' => 'products',
                        'sellable_id' => $this->product->id,
                        'item_name' => $this->product->name,
                        'selling_price' => 200,
                        'discount_amount' => $discount,
                        'quantity' => 1,
                        'currency' => 'EGP',
                        'line_total' => 200 - $discount,
                    ],
                ],
                'item_context' => [
                    'sellable_type' => 'products',
                    'sellable_id' => $this->product->id,
                    'item_name' => $this->product->name,
                    'unit_price' => 200,
                    'quantity' => 1,
                    'currency' => 'EGP',
                    'catalog_max_discount_type' => 'fixed',
                    'catalog_max_discount_value' => 20,
                    'cost_price' => 100,
                ],
                'sale_context' => [
                    'customer_id' => $this->customer->id,
                    'customer_name' => $this->customer->name,
                ],
            ],
        ];
    }

    private function ticketItemRequestPayload(
        int $ticketId,
        int $taskId,
        int $ticketItemId,
        float $discount,
    ): array {
        return [
            'type' => ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT,
            'requested_discount_amount' => $discount,
            'discount_input_type' => 'fixed',
            'discount_input_value' => $discount,
            'cart_subtotal' => 200,
            'payload' => [
                'cart_items' => [
                    [
                        'sellable_type' => 'products',
                        'sellable_id' => $this->product->id,
                        'item_name' => $this->product->name,
                        'selling_price' => 200,
                        'discount_amount' => $discount,
                        'quantity' => 1,
                        'currency' => 'EGP',
                        'line_total' => 200 - $discount,
                    ],
                ],
                'item_context' => [
                    'sellable_type' => 'products',
                    'sellable_id' => $this->product->id,
                    'item_name' => $this->product->name,
                    'unit_price' => 200,
                    'quantity' => 1,
                    'currency' => 'EGP',
                    'catalog_max_discount_type' => 'fixed',
                    'catalog_max_discount_value' => 20,
                    'cost_price' => 100,
                    'ticket_id' => $ticketId,
                    'task_id' => $taskId,
                    'ticket_item_id' => $ticketItemId,
                ],
                'ticket_context' => [
                    'ticket_id' => $ticketId,
                    'customer_name' => $this->customer->name,
                ],
            ],
        ];
    }

    /**
     * @return array{0: Ticket, 1: TicketTask, 2: array<string, mixed>}
     */
    private function createTicketItem(): array
    {
        $brand = Brand::create(['name' => 'Yamaha', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2024,
        ]);
        $bike = CustomerBike::create([
            'customer_id' => $this->customer->id,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN-'.uniqid(),
        ]);

        $ticket = Ticket::create([
            'user_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
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

        Sanctum::actingAs($this->staff);

        $item = $this->postJson("/api/tickets/{$ticket->id}/tasks/{$task->id}/items", [
            'product_id' => $this->product->id,
            'price_snapshot' => 200,
            'qty' => 1,
            'discount' => 0,
        ])->assertCreated()->json();

        return [$ticket, $task, $item];
    }
}
