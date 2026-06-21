<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerIndexLegacySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sellers_index_works_with_legacy_commission_rate_column_only(): void
    {
        foreach ([
            'products_commission_rate',
            'spare_parts_commission_rate',
            'maintenance_parts_commission_rate',
            'bikes_for_sale_commission_rate',
            'maintenance_services_commission_rate',
        ] as $column) {
            if (Schema::hasColumn('sellers', $column)) {
                Schema::table('sellers', function ($table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (! Schema::hasColumn('sellers', 'commission_rate')) {
            Schema::table('sellers', function ($table) {
                $table->decimal('commission_rate', 8, 2)->default(0);
            });
        }

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

    public function test_sellers_index_works_with_partial_seller_rate_columns(): void
    {
        foreach ([
            'spare_parts_commission_rate',
            'maintenance_parts_commission_rate',
            'bikes_for_sale_commission_rate',
            'maintenance_services_commission_rate',
        ] as $column) {
            if (Schema::hasColumn('sellers', $column)) {
                Schema::table('sellers', function ($table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (Schema::hasColumn('sellers', 'commission_rate')) {
            Schema::table('sellers', function ($table) {
                $table->dropColumn('commission_rate');
            });
        }

        app()->forgetInstance(\App\Services\SaleCommissionService::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson('/api/sellers?page=1&sort=rate_high&per_page=20')
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

    public function test_can_create_seller_with_legacy_commission_rate_column_only(): void
    {
        foreach ([
            'products_commission_rate',
            'spare_parts_commission_rate',
            'maintenance_parts_commission_rate',
            'bikes_for_sale_commission_rate',
            'maintenance_services_commission_rate',
        ] as $column) {
            if (Schema::hasColumn('sellers', $column)) {
                Schema::table('sellers', function ($table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        if (! Schema::hasColumn('sellers', 'commission_rate')) {
            Schema::table('sellers', function ($table) {
                $table->decimal('commission_rate', 8, 2)->default(0);
            });
        }

        app()->forgetInstance(\App\Services\SaleCommissionService::class);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/sellers', [
                'name' => 'Legacy Seller',
                'phone' => '01012345678',
                'products_commission_rate' => 5,
                'spare_parts_commission_rate' => 7,
                'maintenance_parts_commission_rate' => 0,
                'bikes_for_sale_commission_rate' => 0,
                'maintenance_services_commission_rate' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Legacy Seller')
            ->assertJsonPath('products_commission_rate', 7)
            ->assertJsonPath('spare_parts_commission_rate', 7);

        $this->assertDatabaseHas('sellers', [
            'name' => 'Legacy Seller',
            'commission_rate' => 7,
        ]);
    }

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
