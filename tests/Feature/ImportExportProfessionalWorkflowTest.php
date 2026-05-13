<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
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
            ->assertJsonFragment([
                'key' => 'brand_name',
                'label' => 'Brand Name',
                'required' => true,
                'type' => 'reference',
                'reference' => 'brands.name',
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
        $this->actingAs($this->admin)
            ->get('/api/import-export/products/export?format=xlsx')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/api/import-export/products/template?format=xlsx')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/api/import-export/products/template?format=csv')
            ->assertOk();
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
