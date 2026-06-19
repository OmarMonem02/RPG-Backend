<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerIndexLegacySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sellers_index_works_without_maintenance_parts_table(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('bike_blueprint_maintenance_parts');
        Schema::dropIfExists('maintenance_parts');
        Schema::dropIfExists('maintenance_part_categories');

        if (Schema::hasColumn('sale_items', 'maintenance_part_id')) {
            Schema::table('sale_items', function ($table) {
                $table->dropForeign(['maintenance_part_id']);
                $table->dropColumn('maintenance_part_id');
            });
        }
        Schema::enableForeignKeyConstraints();

        app()->forgetInstance(\App\Services\SaleCommissionService::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/sellers?page=1&sort=newest&per_page=20')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'summary' => [
                    'total_sellers',
                    'commission_base',
                    'commission_amount',
                ],
            ]);
    }
}
