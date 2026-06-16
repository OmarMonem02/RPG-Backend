<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Seller;
use App\Models\User;
use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalRequestTest extends TestCase
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

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
        ]);

        $this->staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@example.com',
            'password' => bcrypt('password'),
            'role' => User::ROLE_STAFF,
        ]);

        $this->customer = Customer::create([
            'name' => 'Customer One',
            'phone' => '01000000001',
        ]);

        $this->seller = Seller::create([
            'name' => 'Seller One',
            'phone' => '01111111111',
            'commission_rate' => 5,
        ]);

        $this->paymentMethod = PaymentMethod::create(['name' => 'Cash']);

        $brand = Brand::create(['name' => 'Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Accessories']);

        $this->product = Product::create([
            'name' => 'Helmet',
            'sku' => 'PR-001',
            'stock_quantity' => 10,
            'low_stock_alarm' => 1,
            'products_category_id' => $category->id,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 200,
            'brand_id' => $brand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 20,
        ]);
    }

    public function test_staff_can_create_sale_discount_request_and_admin_can_approve(): void
    {
        $payload = $this->requestPayload();

        $createResponse = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $payload)
            ->assertCreated()
            ->assertJsonPath('status', ApprovalRequest::STATUS_PENDING)
            ->assertJsonPath('requested_discount_amount', 20);

        $requestId = $createResponse->json('id');

        $this->actingAs($this->admin)
            ->getJson('/api/approval-requests?status=pending')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $requestId);

        $this->actingAs($this->admin)
            ->postJson("/api/approval-requests/{$requestId}/approve", [
                'approved_discount_amount' => 15,
                'approved_discount_input_type' => 'fixed',
                'approved_discount_input_value' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('status', ApprovalRequest::STATUS_APPROVED)
            ->assertJsonPath('approved_discount_amount', 15);

        $this->actingAs($this->staff)
            ->getJson("/api/approval-requests/{$requestId}")
            ->assertOk()
            ->assertJsonPath('status', ApprovalRequest::STATUS_APPROVED);
    }

    public function test_staff_cannot_create_sale_with_discount_without_approved_request(): void
    {
        $salePayload = $this->salePayload(20);

        $this->actingAs($this->staff)
            ->postJson('/api/sales', $salePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_approval_request_id']);
    }

    public function test_staff_can_create_sale_with_approved_discount_request(): void
    {
        $requestId = $this->createApprovedRequest(20);

        $salePayload = $this->salePayload(20, $requestId);

        $this->actingAs($this->staff)
            ->postJson('/api/sales', $salePayload)
            ->assertCreated()
            ->assertJsonPath('discount', 20);

        $this->assertDatabaseHas('approval_requests', [
            'id' => $requestId,
            'status' => ApprovalRequest::STATUS_CONSUMED,
        ]);
    }

    public function test_approved_request_cannot_be_reused(): void
    {
        $requestId = $this->createApprovedRequest(20);

        $salePayload = $this->salePayload(20, $requestId);

        $this->actingAs($this->staff)
            ->postJson('/api/sales', $salePayload)
            ->assertCreated();

        $this->actingAs($this->staff)
            ->postJson('/api/sales', $salePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_approval_request_id']);
    }

    public function test_new_request_cancels_previous_pending_request(): void
    {
        $first = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->requestPayload(10))
            ->assertCreated()
            ->json('id');

        $second = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->requestPayload(25))
            ->assertCreated()
            ->json('id');

        $this->assertDatabaseHas('approval_requests', [
            'id' => $first,
            'status' => ApprovalRequest::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('approval_requests', [
            'id' => $second,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
    }

    public function test_admin_can_reject_request(): void
    {
        $requestId = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->requestPayload())
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/approval-requests/{$requestId}/reject", [
                'rejection_reason' => 'Too high for this customer.',
            ])
            ->assertOk()
            ->assertJsonPath('status', ApprovalRequest::STATUS_REJECTED)
            ->assertJsonPath('rejection_reason', 'Too high for this customer.');
    }

    public function test_pending_count_is_admin_only(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->requestPayload())
            ->assertCreated();

        $this->actingAs($this->staff)
            ->getJson('/api/approval-requests/pending-count')
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->getJson('/api/approval-requests/pending-count')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    private function requestPayload(float $amount = 20): array
    {
        return [
            'type' => ApprovalRequest::TYPE_SALE_DISCOUNT,
            'requested_discount_amount' => $amount,
            'discount_input_type' => 'fixed',
            'discount_input_value' => $amount,
            'cart_subtotal' => 400,
            'payload' => [
                'cart_items' => [
                    [
                        'sellable_type' => 'products',
                        'sellable_id' => $this->product->id,
                        'item_name' => $this->product->name,
                        'selling_price' => 200,
                        'discount_amount' => 0,
                        'quantity' => 2,
                        'currency' => 'EGP',
                        'line_total' => 400,
                    ],
                ],
                'sale_context' => [
                    'customer_id' => $this->customer->id,
                    'customer_name' => $this->customer->name,
                    'seller_id' => $this->seller->id,
                    'sale_type' => 'site',
                    'shipping_fee' => 0,
                    'is_maintenance' => false,
                ],
            ],
        ];
    }

    private function createApprovedRequest(float $amount): int
    {
        $requestId = $this->actingAs($this->staff)
            ->postJson('/api/approval-requests', $this->requestPayload($amount))
            ->assertCreated()
            ->json('id');

        $this->actingAs($this->admin)
            ->postJson("/api/approval-requests/{$requestId}/approve", [
                'approved_discount_amount' => $amount,
                'approved_discount_input_type' => 'fixed',
                'approved_discount_input_value' => $amount,
            ])
            ->assertOk();

        return $requestId;
    }

    private function salePayload(float $discount, ?int $requestId = null): array
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'payment_method_id' => $this->paymentMethod->id,
            'type' => 'site',
            'status' => 'completed',
            'shipping_fee' => 0,
            'discount' => $discount,
            'is_maintenance' => false,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'selling_price' => 200,
                    'discount' => 0,
                    'qty' => 2,
                ],
            ],
        ];

        if ($requestId !== null) {
            $payload['discount_approval_request_id'] = $requestId;
        }

        return $payload;
    }
}
