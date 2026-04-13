<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_settings_payload(): void
    {
        Setting::query()->create(['key' => 'tax_rate', 'value' => 14]);
        Setting::query()->create(['key' => 'currency', 'value' => 'EGP']);
        Setting::query()->create(['key' => 'exchange_rate', 'value' => 50.25]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJson([
                'tax_rate' => 14.0,
                'currency' => 'EGP',
                'exchange_rate' => 50.25,
            ]);
    }

    public function test_admin_can_upsert_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/settings', [
                'tax_rate' => 14,
                'currency' => 'USD',
                'exchange_rate' => 51.75,
            ])
            ->assertOk()
            ->assertJson([
                'tax_rate' => 14.0,
                'currency' => 'USD',
                'exchange_rate' => 51.75,
            ]);

        $this->assertDatabaseHas('settings', ['key' => 'tax_rate', 'value' => 14]);
        $this->assertDatabaseHas('settings', ['key' => 'currency', 'value' => 'USD']);
        $this->assertDatabaseHas('settings', ['key' => 'exchange_rate', 'value' => 51.75]);
    }

    public function test_settings_update_validates_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/settings', [
                'currency' => 'EUR',
                'exchange_rate' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['currency', 'exchange_rate']);
    }

    public function test_settings_update_requires_at_least_one_supported_field(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/settings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)
            ->putJson('/api/settings', [
                'tax_rate' => 14,
            ])
            ->assertStatus(403);
    }
}
