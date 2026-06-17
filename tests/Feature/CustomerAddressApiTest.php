<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Support\UserPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressApiTest extends TestCase
{
    use RefreshDatabase;

    private function matrix(array $overrides): array
    {
        return UserPermissions::normalizeMatrix(array_replace(
            array_fill_keys(UserPermissions::pages(), []),
            $overrides
        ));
    }

    public function test_can_list_customer_addresses_with_sales_read(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->matrix([
                'sales' => ['read'],
            ]),
        ]);

        $customer = Customer::create([
            'name' => 'Address Customer',
            'phone' => '01005556677',
        ]);

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => 'Home',
            'full_address' => '12 Nile Street',
            'city' => 'Cairo',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->getJson("/api/customers/{$customer->id}/addresses")
            ->assertOk()
            ->assertJsonPath('data.0.id', $address->id)
            ->assertJsonPath('data.0.full_address', '12 Nile Street')
            ->assertJsonPath('data.0.city', 'Cairo');
    }

    public function test_can_create_customer_address_with_sales_create(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'permissions_override' => $this->matrix([
                'sales' => ['create'],
            ]),
        ]);

        $customer = Customer::create([
            'name' => 'New Address Customer',
            'phone' => '01006667788',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/customers/{$customer->id}/addresses", [
                'label' => 'Office',
                'full_address' => '45 Garden City',
                'city' => 'Giza',
            ]);

        $response->assertCreated()
            ->assertJsonPath('full_address', '45 Garden City')
            ->assertJsonPath('city', 'Giza')
            ->assertJsonPath('is_default', true);

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'full_address' => '45 Garden City',
            'city' => 'Giza',
            'is_default' => true,
        ]);

        $customer->refresh();
        $this->assertSame('45 Garden City, Giza', $customer->address);
    }

    public function test_create_address_requires_full_address_and_city(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $customer = Customer::create([
            'name' => 'Validation Customer',
            'phone' => '01007778899',
        ]);

        $this->actingAs($admin)
            ->postJson("/api/customers/{$customer->id}/addresses", [
                'label' => 'Home',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_address', 'city']);
    }
}
