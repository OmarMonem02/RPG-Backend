<?php

namespace Tests\Feature;

use App\Models\BikeBlueprint;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportExportIdempotencyTest extends TestCase
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

    public function test_brands_import_skips_existing_and_same_file_duplicates(): void
    {
        $csv = $this->csvUpload(implode("\n", [
            'name,type',
            'Yamaha,bikes',
            'Yamaha,bikes',
            'Honda,bikes',
        ]));

        $first = $this->actingAs($this->admin)
            ->post('/api/import-export/brands/import', ['file' => $csv]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(2, Brand::count());

        $second = $this->actingAs($this->admin)
            ->post('/api/import-export/brands/import', ['file' => $this->csvUpload(implode("\n", [
                'name,type',
                'Yamaha,bikes',
                'Honda,bikes',
                'Suzuki,bikes',
            ]))]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 2);

        $this->assertSame(3, Brand::count());
        $this->assertNotEmpty($second->json('skipped_duplicates'));
    }

    public function test_bike_blueprints_import_skips_duplicates_for_brand_name(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'type' => 'bikes']);

        $first = $this->actingAs($this->admin)->post('/api/import-export/bike_blueprints/import', [
            'file' => $this->csvUpload(implode("\n", [
                'brand_name,model,year',
                'Yamaha,R1,2024',
                'Yamaha,R1,2024',
                'Yamaha,R6,2025',
            ])),
        ]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(2, BikeBlueprint::count());

        $second = $this->actingAs($this->admin)->post('/api/import-export/bike_blueprints/import', [
            'file' => $this->csvUpload(implode("\n", [
                'brand_name,model,year',
                'Yamaha,R1,2024',
                'Yamaha,MT-07,2026',
            ])),
        ]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertDatabaseHas('bike_blueprints', [
            'brand_id' => $brand->id,
            'model' => 'MT-07',
            'year' => 2026,
        ]);
    }

    public function test_maintenance_services_import_skips_existing_and_new_rows_are_created(): void
    {
        $sector = MaintenanceServiceSector::create(['name' => 'Workshop']);

        $first = $this->actingAs($this->admin)->post('/api/import-export/maintenance_services/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,currency_pricing,service_price,max_discount_type,max_discount_value,sector_name',
                "Oil Change,EGP,250,fixed,0,{$sector->name}",
                "Oil Change,EGP,250,fixed,0,{$sector->name}",
                "Brake Service,EGP,400,percentage,10,{$sector->name}",
            ])),
        ]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $second = $this->actingAs($this->admin)->post('/api/import-export/maintenance_services/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,currency_pricing,service_price,max_discount_type,max_discount_value,sector_name',
                "Brake Service,EGP,400,percentage,10,{$sector->name}",
                "Chain Service,EGP,175,fixed,0,{$sector->name}",
            ])),
        ]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(3, MaintenanceService::count());
    }

    public function test_products_import_skips_duplicate_skus(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $first = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                "Helmet A,SKU-100,P-100,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,yes,First",
                "Helmet A Duplicate,SKU-100,P-100,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,yes,Duplicate",
                "Helmet B,SKU-200,P-200,4,1,{$category->name},EGP,110,170,{$brand->name},fixed,0,no,Second",
            ])),
        ]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $second = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                "Helmet B Again,SKU-200,P-200,4,1,{$category->name},EGP,110,170,{$brand->name},fixed,0,no,Existing",
                "Helmet C,SKU-300,P-300,7,2,{$category->name},EGP,120,190,{$brand->name},percentage,5,no,New",
            ])),
        ]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(3, Product::count());
    }

    public function test_spare_parts_import_skips_duplicate_skus_and_duplicate_pivot_assignments(): void
    {
        $brand = Brand::create(['name' => 'Bosch', 'type' => 'spare_parts']);
        $category = SparePartCategory::create(['name' => 'Brakes']);
        $bikeBrand = Brand::create(['name' => 'BMW', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'GS 1250',
            'year' => 2024,
        ]);

        $first = $this->actingAs($this->admin)->post('/api/import-export/spare_parts/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,bike_blueprints',
                "Brake Pad,SP-100,BP-1,10,2,{$category->name},EGP,50,90,{$brand->name},fixed,0,no,Main,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
                "Brake Pad Duplicate,SP-100,BP-1,10,2,{$category->name},EGP,50,90,{$brand->name},fixed,0,no,Duplicate,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
                "Chain,SP-200,CH-1,6,1,{$category->name},EGP,70,120,{$brand->name},fixed,0,yes,Second,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
            ])),
        ]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(2, SparePart::count());
        $this->assertSame(1, $blueprint->fresh()->spareParts()->where('sku', 'SP-100')->count());

        $second = $this->actingAs($this->admin)->post('/api/import-export/spare_parts/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,bike_blueprints',
                "Chain Again,SP-200,CH-1,6,1,{$category->name},EGP,70,120,{$brand->name},fixed,0,yes,Existing,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
                "Filter,SP-300,FT-1,3,1,{$category->name},EGP,30,60,{$brand->name},fixed,0,no,New,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
            ])),
        ]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(3, SparePart::count());
    }

    public function test_bikes_import_skips_duplicate_vins(): void
    {
        $brand = Brand::create(['name' => 'Kawasaki', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $brand->id,
            'model' => 'Ninja ZX-6R',
            'year' => 2025,
        ]);

        $first = $this->actingAs($this->admin)->post('/api/import-export/bikes/import', [
            'file' => $this->csvUpload(implode("\n", [
                'brand_name,model,year,vin,mileage,status,currency_pricing,cost_price,sale_price,max_discount_type,max_discount_value,notes',
                "{$brand->name},{$blueprint->model},{$blueprint->year},VIN-100,1000,available,EGP,100000,120000,fixed,0,First",
                "{$brand->name},{$blueprint->model},{$blueprint->year},VIN-100,1000,available,EGP,100000,120000,fixed,0,Duplicate",
                "{$brand->name},{$blueprint->model},{$blueprint->year},VIN-200,2500,available,EGP,90000,115000,percentage,5,Second",
            ])),
        ]);

        $first->assertOk()
            ->assertJsonPath('created_count', 2)
            ->assertJsonPath('skipped_count', 1);

        $second = $this->actingAs($this->admin)->post('/api/import-export/bikes/import', [
            'file' => $this->csvUpload(implode("\n", [
                'brand_name,model,year,vin,mileage,status,currency_pricing,cost_price,sale_price,max_discount_type,max_discount_value,notes',
                "{$brand->name},{$blueprint->model},{$blueprint->year},VIN-200,2500,available,EGP,90000,115000,percentage,5,Existing",
                "{$brand->name},{$blueprint->model},{$blueprint->year},VIN-300,0,available,EGP,95000,118000,fixed,0,New",
            ])),
        ]);

        $second->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertSame(3, BikeForSale::count());
    }

    public function test_import_restores_soft_deleted_brand_instead_of_skipping_it(): void
    {
        $brand = Brand::create(['name' => 'Restorable', 'type' => 'bikes']);
        $brand->delete();

        $response = $this->actingAs($this->admin)->post('/api/import-export/brands/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,type',
                'Restorable,bikes',
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('restored_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Restorable',
            'type' => 'bikes',
            'deleted_at' => null,
        ]);
    }

    public function test_import_restores_soft_deleted_spare_part_and_relinks_blueprints(): void
    {
        $brand = Brand::create(['name' => 'Restore Brand', 'type' => 'spare_parts']);
        $category = SparePartCategory::create(['name' => 'Restore Category']);
        $bikeBrand = Brand::create(['name' => 'Restore Bike', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Restore Model',
            'year' => 2026,
        ]);

        $sparePart = SparePart::create([
            'name' => 'Restorable Part',
            'sku' => 'RESTORE-100',
            'part_number' => 'RP-1',
            'stock_quantity' => 2,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 20,
            'sale_price' => 30,
            'brand_id' => $brand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
            'notes' => 'deleted',
        ]);
        $sparePart->delete();

        $response = $this->actingAs($this->admin)->post('/api/import-export/spare_parts/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,bike_blueprints',
                "Restored Part,RESTORE-100,RP-2,8,2,{$category->name},EGP,25,40,{$brand->name},fixed,0,no,restored,{$bikeBrand->name} | {$blueprint->model} | {$blueprint->year}",
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('restored_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseHas('spare_parts', [
            'id' => $sparePart->id,
            'name' => 'Restored Part',
            'part_number' => 'RP-2',
            'deleted_at' => null,
        ]);
        $this->assertSame(1, $blueprint->fresh()->spareParts()->where('spare_parts.id', $sparePart->id)->count());
    }

    private function csvUpload(string $content): UploadedFile
    {
        $directory = storage_path('app/testing-imports');

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, basename($path), 'text/csv', null, true);
    }
}
