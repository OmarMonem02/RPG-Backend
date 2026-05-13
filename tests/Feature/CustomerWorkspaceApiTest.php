<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerBike;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWorkspaceApiTest extends TestCase
{
    use RefreshDatabase;

    private function matrix(array $overrides): array
    {
        return UserPermissions::normalizeMatrix(array_replace(
            array_fill_keys(UserPermissions::pages(), []),
            $overrides
        ));
    }

    public function test_customers_index_forbidden_without_sales_or_maintenance_read(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => $this->matrix([
                'reporting' => ['read'],
            ]),
        ]);

        $this->actingAs($user)
            ->getJson('/api/customers')
            ->assertForbidden();
    }

    public function test_customers_index_allowed_with_sales_read(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->matrix([
                'sales' => ['read'],
            ]),
        ]);

        Customer::create(['name' => 'Alpha', 'phone' => '01001111111']);

        $this->actingAs($user)
            ->getJson('/api/customers?search=Alpha')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha');
    }

    public function test_workspace_allowed_with_maintenance_read_and_returns_sections(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TECHNICIAN,
            'permissions_override' => $this->matrix([
                'maintenance' => ['read'],
            ]),
        ]);

        $customer = Customer::create(['name' => 'Beta', 'phone' => '01002222222']);
        $payment = PaymentMethod::create(['name' => 'Cash']);

        Sale::create([
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'payment_method_id' => $payment->id,
            'type' => 'site',
            'status' => 'completed',
            'total' => 150.50,
        ]);

        $bikeBrand = Brand::create(['name' => 'Trail', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'X1',
            'year' => 2024,
        ]);
        $bike = CustomerBike::create([
            'customer_id' => $customer->id,
            'bike_blueprint_id' => $blueprint->id,
            'vin' => 'VIN123',
        ]);

        Ticket::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'customer_bike_id' => $bike->id,
            'status' => 'pending',
            'total' => 0,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/customers/{$customer->id}/workspace");

        $response->assertOk()
            ->assertJsonPath('customer.name', 'Beta')
            ->assertJsonPath('stats.bikes_count', 1)
            ->assertJsonPath('stats.sales_count', 1)
            ->assertJsonPath('stats.tickets_open_count', 1)
            ->assertJsonPath('stats.lifetime_sales_total', 150.5)
            ->assertJsonPath('bikes.0.vin', 'VIN123')
            ->assertJsonPath('sales.data.0.customer_id', $customer->id)
            ->assertJsonPath('tickets.data.0.customer_id', $customer->id);
    }

    public function test_admin_can_still_create_customer_via_entity_controller(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/customers', [
                'name' => 'New Person',
                'phone' => '01009999999',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'New Person');
    }

    public function test_non_admin_cannot_post_customers(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->matrix([
                'sales' => ['read', 'create'],
            ]),
        ]);

        $this->actingAs($staff)
            ->postJson('/api/customers', [
                'name' => 'Blocked',
                'phone' => '01008888888',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_customer_with_all_profile_fields(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/customers', [
                'name' => 'Complete Profile',
                'phone' => '01005551234',
                'address' => '15 Tahrir St, Cairo',
                'how_did_you_know_us' => 'Instagram ad',
                'notes' => 'Prefers morning calls.',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Complete Profile')
            ->assertJsonPath('phone', '01005551234')
            ->assertJsonPath('address', '15 Tahrir St, Cairo')
            ->assertJsonPath('how_did_you_know_us', 'Instagram ad')
            ->assertJsonPath('notes', 'Prefers morning calls.');

        $this->assertDatabaseHas('customers', [
            'name' => 'Complete Profile',
            'phone' => '01005551234',
            'how_did_you_know_us' => 'Instagram ad',
        ]);
    }
}
