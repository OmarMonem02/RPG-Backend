<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandValidationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_brand_returns_field_errors(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Brand::create(['name' => 'Honda', 'types' => ['bikes']]);

        $response = $this->actingAs($admin)->postJson('/api/brands', [
            'name' => 'HONDA',
            'types' => ['products'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'A brand with this name already exists.');
    }
}
