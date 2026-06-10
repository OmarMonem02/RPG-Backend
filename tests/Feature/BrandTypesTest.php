<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_brand_can_have_multiple_types(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/brands', [
            'name' => 'Honda',
            'types' => ['bikes', 'spare_parts', 'products'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('types', ['bikes', 'spare_parts', 'products']);

        $this->assertDatabaseHas('brands', [
            'name' => 'Honda',
            'types' => json_encode(['bikes', 'spare_parts', 'products']),
        ]);
    }

    public function test_brand_list_filters_by_type(): void
    {
        Brand::create(['name' => 'Multi', 'types' => ['bikes', 'spare_parts']]);
        Brand::create(['name' => 'Products Only', 'types' => ['products']]);

        $response = $this->actingAs($this->admin)->getJson('/api/brands?type=spare_parts');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Multi'], $names);
    }

    public function test_brand_name_must_be_unique(): void
    {
        Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);

        $response = $this->actingAs($this->admin)->postJson('/api/brands', [
            'name' => 'Yamaha',
            'types' => ['products'],
        ]);

        $response->assertStatus(422);
    }

    public function test_brand_name_must_be_unique_case_insensitive(): void
    {
        Brand::create(['name' => 'Honda', 'types' => ['bikes']]);

        $response = $this->actingAs($this->admin)->postJson('/api/brands', [
            'name' => 'HONDA',
            'types' => ['products'],
        ]);

        $response->assertStatus(422);
    }
}
