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
        Setting::query()->create(['key' => 'exchange_rate', 'value' => 50.25]);
        Setting::query()->create(['key' => 'exchange_rate_eur', 'value' => 52.1]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJson([
                'tax_rate' => 14.0,
                'exchange_rate' => 50.25,
                'exchange_rate_eur' => 52.1,
            ]);
    }

    public function test_admin_can_upsert_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/settings', [
                'tax_rate' => 14,
                'exchange_rate' => 51.75,
                'exchange_rate_eur' => 53.0,
            ])
            ->assertOk()
            ->assertJson([
                'tax_rate' => 14.0,
                'exchange_rate' => 51.75,
                'exchange_rate_eur' => 53.0,
            ]);

        $this->assertDatabaseHas('settings', ['key' => 'tax_rate', 'value' => '14']);
        $this->assertDatabaseHas('settings', ['key' => 'exchange_rate', 'value' => '51.75']);
        $this->assertDatabaseHas('settings', ['key' => 'exchange_rate_eur', 'value' => '53']);
    }

    public function test_settings_update_validates_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->putJson('/api/settings', [
                'exchange_rate' => 0,
                'exchange_rate_eur' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exchange_rate', 'exchange_rate_eur']);
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
