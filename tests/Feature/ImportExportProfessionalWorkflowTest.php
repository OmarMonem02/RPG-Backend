<?php

namespace Tests\Feature;

use App\Exports\SparePartsExport;
use App\Models\BikeBlueprint;
use App\Support\ImportExport\ImportExportDefinitions;
use App\Support\ImportExport\ImportRowProcessor;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportExportProfessionalWorkflowTest extends TestCase
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

    public function test_entities_endpoint_returns_structured_column_metadata(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/import-export/entities');

        $response->assertOk()
            ->assertJsonCount(9)
            ->assertJsonFragment([
                'key' => 'brand_name',
                'label' => 'Brand Name',
                'required' => true,
                'type' => 'reference',
                'reference' => 'brands.name',
            ])
            ->assertJsonFragment([
                'key' => 'tags',
                'label' => 'Tags',
                'type' => 'tag_list',
            ])
            ->assertJsonFragment([
                'slug' => 'product_categories',
                'label' => 'Product Categories',
            ])
            ->assertJsonFragment([
                'export' => '/import-export/products/export',
            ]);
    }

    public function test_parse_validates_without_writing_and_reports_row_statuses(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/parse', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                "Helmet A,SKU-100,P-100,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,yes,Ready",
                "Helmet B,SKU-200,P-200,4,1,Missing Category,EGP,100,150,{$brand->name},fixed,0,no,Bad reference",
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.total_rows', 2)
            ->assertJsonPath('summary.valid_count', 1)
            ->assertJsonPath('summary.invalid_count', 1)
            ->assertJsonPath('rows.0.status', 'valid')
            ->assertJsonPath('rows.1.status', 'invalid');

        $this->assertSame(0, Product::count());
    }

    public function test_import_saves_valid_rows_and_skips_invalid_rows(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                "Helmet A,SKU-100,P-100,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,yes,Ready",
                "Helmet B,SKU-200,P-200,4,1,Missing Category,EGP,100,150,{$brand->name},fixed,0,no,Bad reference",
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);

        $this->assertDatabaseHas('products', ['sku' => 'SKU-100']);
        $this->assertDatabaseMissing('products', ['sku' => 'SKU-200']);
    }

    public function test_export_and_template_endpoints_respond_successfully(): void
    {
        $brand = Brand::create(['name' => 'Export Brand', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Export Category']);
        Product::create([
            'name' => 'Export Product',
            'sku' => 'EXPORT-SKU-1',
            'products_category_id' => $category->id,
            'brand_id' => $brand->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);

        $exportResponse = $this->actingAs($this->admin)
            ->get('/api/import-export/products/export?format=xlsx');

        $exportResponse->assertOk();
        $this->assertStringContainsString(
            'spreadsheet',
            (string) $exportResponse->headers->get('Content-Type'),
        );

        $path = storage_path('app/testing-export-products.xlsx');
        file_put_contents($path, $exportResponse->getContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        $this->assertGreaterThan(1, $sheet->getHighestRow());
        $this->assertSame('EXPORT-SKU-1', $sheet->getCell('C2')->getValue());

        $this->actingAs($this->admin)
            ->get('/api/import-export/products/template?format=xlsx')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/api/import-export/products/template?format=csv')
            ->assertOk();
    }

    public function test_product_import_persists_tags_and_image_url(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'type' => 'products']);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,tags,image',
                "Helmet A,SKU-TAGS,P-100,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,yes,Ready,Matte Black; High Load,https://example.com/helmet.jpg",
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);

        $product = Product::where('sku', 'SKU-TAGS')->first();
        $this->assertNotNull($product);
        $this->assertSame(['Matte Black', 'High Load'], $product->tags);
        $this->assertSame('https://example.com/helmet.jpg', $product->image);
        $this->assertNull($product->image_public_id);
    }

    public function test_spare_parts_import_persists_image_from_export_style_headers(): void
    {
        $brand = Brand::create(['name' => 'LS2', 'type' => 'spare_parts']);
        $category = SparePartCategory::create(['name' => 'Brakes']);
        $imageUrl = 'https://res.cloudinary.com/demo/image/upload/v123/rpg-system/spare-parts/sample-part.jpg';

        $response = $this->actingAs($this->admin)->post('/api/import-export/spare_parts/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,currency_pricing,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,image_url',
                "Brake Pad,SP-IMG-1,P-1,5,1,{$category->name},EGP,100,150,{$brand->name},fixed,0,no,Ready,{$imageUrl}",
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);

        $part = SparePart::where('sku', 'SP-IMG-1')->first();
        $this->assertNotNull($part);
        $this->assertSame($imageUrl, $part->image);
        $this->assertSame('rpg-system/spare-parts/sample-part', $part->image_public_id);
    }

    public function test_bike_import_rejects_invalid_status(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'type' => 'bikes']);
        BikeBlueprint::create(['brand_id' => $brand->id, 'model' => 'R1', 'year' => 2024]);

        $response = $this->actingAs($this->admin)->post('/api/import-export/bikes/parse', [
            'file' => $this->csvUpload(implode("\n", [
                'brand_name,model,year,vin,mileage,status,currency_pricing,cost_price,sale_price,max_discount_type,max_discount_value,notes',
                'Yamaha,R1,2024,VIN-INVALID,100,scrapped,EGP,1000,1500,fixed,0,Bad status',
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('summary.invalid_count', 1)
            ->assertJsonPath('rows.0.status', 'invalid');

        $this->assertSame(0, BikeForSale::count());
    }

    public function test_product_category_import_creates_record(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/import-export/product_categories/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name',
                'Helmets',
                'Gloves',
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 2);
        $this->assertDatabaseHas('product_categories', ['name' => 'Helmets']);
        $this->assertDatabaseHas('product_categories', ['name' => 'Gloves']);
    }

    public function test_product_category_import_skips_duplicate(): void
    {
        ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/product_categories/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name',
                'Helmets',
                'Gloves',
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('created_count', 1)
            ->assertJsonPath('skipped_count', 1);
    }

    public function test_product_category_import_restores_soft_deleted_record(): void
    {
        $category = ProductCategory::create(['name' => 'RestorableCategory']);
        $category->delete();

        $response = $this->actingAs($this->admin)->post('/api/import-export/product_categories/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name',
                'RestorableCategory',
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('rows.0.status', 'restored')
            ->assertJsonPath('restored_count', 1)
            ->assertJsonPath('skipped_count', 0);

        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'RestorableCategory',
            'deleted_at' => null,
        ]);
    }

    public function test_spare_part_category_import_creates_record(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/import-export/spare_part_categories/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name',
                'Chains',
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);
        $this->assertDatabaseHas('spare_part_categories', ['name' => 'Chains']);
    }

    public function test_maintenance_sector_import_creates_record(): void
    {
        $response = $this->actingAs($this->admin)->post('/api/import-export/maintenance_service_sectors/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name',
                'Engine',
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);
        $this->assertDatabaseHas('maintenance_service_sectors', ['name' => 'Engine']);
    }

    public function test_spare_parts_parse_accepts_excel_typed_sku_and_universal_values(): void
    {
        $brand = Brand::create(['name' => 'LS2', 'type' => 'spare_parts']);
        $category = SparePartCategory::create(['name' => 'Brakes']);
        $bikeBrand = Brand::create(['name' => 'Honda', 'type' => 'bikes']);
        BikeBlueprint::create(['brand_id' => $bikeBrand->id, 'model' => 'MT-07', 'year' => 2020]);

        $definition = (new ImportExportDefinitions())->get('spare_parts');
        $processor = new ImportRowProcessor();

        $result = $processor->process('spare_parts', $definition, [
            'name' => 'Item-Test-Spare Part',
            'sku' => 1231231231,
            'part_number' => 'ITSP-1',
            'stock_quantity' => 20,
            'low_stock_alarm' => 1,
            'category_name' => $category->name,
            'currency_pricing' => 'EGP',
            'cost_price' => 210,
            'sale_price' => 250,
            'brand_name' => $brand->name,
            'max_discount_type' => 'percentage',
            'max_discount_value' => 10,
            'universal' => false,
            'notes' => 'OEM dsdsdsds',
            'bike_blueprints' => 'Honda | MT-07 | 2020',
            'tags' => 'Black; Matte Black; Plastic',
            'image' => 'https://example.com/part.jpg',
        ], 2, false);

        $this->assertSame('valid', $result['status']);
        $this->assertSame([], $result['issues']);
        $this->assertSame('1231231231', $result['data']['sku']);
        $this->assertSame('no', $result['data']['universal']);
    }

    public function test_spare_parts_export_includes_compatible_bike_blueprints(): void
    {
        $brand = Brand::create(['name' => 'Part Brand', 'type' => 'spare_parts']);
        $category = SparePartCategory::create(['name' => 'Export Category']);
        $bikeBrand = Brand::create(['name' => 'Bike Brand', 'type' => 'bikes']);
        $blueprint = BikeBlueprint::create([
            'brand_id' => $bikeBrand->id,
            'model' => 'Export Model',
            'year' => 2025,
        ]);

        $part = SparePart::create([
            'name' => 'Export Part',
            'sku' => 'EXPORT-100',
            'part_number' => 'EP-1',
            'stock_quantity' => 1,
            'low_stock_alarm' => 1,
            'spare_parts_category_id' => $category->id,
            'currency_pricing' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'brand_id' => $brand->id,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => false,
        ]);
        $part->bikeBlueprints()->sync([$blueprint->id]);
        $part->load(['category', 'brand', 'bikeBlueprints.brand']);

        $row = (new SparePartsExport())->map($part);
        $headings = (new SparePartsExport())->headings();
        $blueprintIndex = array_search('bike_blueprints', $headings, true);

        $this->assertNotFalse($blueprintIndex);
        $this->assertSame('Bike Brand | Export Model | 2025', $row[$blueprintIndex]);
    }

    private function csvUpload(string $content): UploadedFile
    {
        $directory = storage_path('app/testing-imports');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . uniqid('import_', true) . '.csv';
        file_put_contents($path, $content);

        return new UploadedFile($path, basename($path), 'text/csv', null, true);
    }
}
