<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\History;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_history_with_formatted_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $customer = Customer::create([
            'name' => 'History Customer',
            'phone' => '01000000099',
        ]);

        $customer->update(['name' => 'History Customer Updated']);

        $response = $this->actingAs($admin)->getJson('/api/history');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'action',
                        'entity_type',
                        'entity_label',
                        'model_type',
                        'model_id',
                        'summary',
                        'changes',
                        'changes_count',
                        'user',
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'summary' => ['total', 'creates', 'updates', 'deletes'],
                'entities',
            ]);

        $updateEntry = collect($response->json('data'))
            ->first(fn (array $row) => ($row['action'] ?? null) === 'update'
                && ($row['entity_type'] ?? null) === 'customer');

        $this->assertNotNull($updateEntry);
        $this->assertSame('Customer', $updateEntry['entity_label']);
        $this->assertContains('Name: History Customer → History Customer Updated', $updateEntry['summary']);
        $this->assertSame('/customers/' . $customer->id, $updateEntry['entity_path']);
    }

    public function test_staff_cannot_access_history(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)
            ->getJson('/api/history')
            ->assertForbidden();
    }

    public function test_history_filters_by_entity_type_and_action(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $customer = Customer::create([
            'name' => 'Filter Customer',
            'phone' => '01000000098',
        ]);

        History::query()->delete();

        History::create([
            'user_id' => $admin->id,
            'model_type' => Customer::class,
            'model_id' => $customer->id,
            'action' => 'update',
            'before' => ['notes' => 'old'],
            'after' => ['notes' => 'new'],
            'ip_address' => '127.0.0.1',
        ]);

        History::create([
            'user_id' => $admin->id,
            'model_type' => User::class,
            'model_id' => $admin->id,
            'action' => 'create',
            'before' => null,
            'after' => ['name' => $admin->name],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/history?entity_type=customer&action=update');

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame('customer', $rows[0]['entity_type']);
        $this->assertSame('update', $rows[0]['action']);
    }

    public function test_history_filters_by_date_from(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        History::query()->delete();

        $old = History::create([
            'user_id' => $admin->id,
            'model_type' => Customer::class,
            'model_id' => 1,
            'action' => 'create',
            'before' => null,
            'after' => ['name' => 'Old'],
            'ip_address' => '127.0.0.1',
        ]);
        $old->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->save();

        History::create([
            'user_id' => $admin->id,
            'model_type' => Customer::class,
            'model_id' => 2,
            'action' => 'create',
            'before' => null,
            'after' => ['name' => 'Recent'],
            'ip_address' => '127.0.0.1',
        ]);

        $dateFrom = now()->subDay()->toDateString();

        $response = $this->actingAs($admin)->getJson('/api/history?date_from=' . $dateFrom);

        $response->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('data.0.model_id'));
    }
}
