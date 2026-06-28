<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExportColumnOrderingTest extends TestCase
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

    public function test_export_columns_endpoint_returns_all_contexts(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/export-columns');

        $response->assertOk()
            ->assertJsonStructure([
                'import_export' => ['products' => ['label', 'columns']],
                'sales' => ['label', 'columns'],
                'sale_items' => ['label', 'columns'],
                'stocktake' => ['label', 'columns'],
                'history' => ['label', 'columns'],
            ])
            ->assertJsonPath('import_export.products.columns.0.key', 'id')
            ->assertJsonPath('import_export.products.columns.0.export_only', true)
            ->assertJsonPath('sales.columns.0.key', 'sale_id');
    }

    public function test_product_export_with_custom_columns_produces_ordered_headers(): void
    {
        $brand = Brand::create(['name' => 'Export Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Export Category']);
        Product::create([
            'name' => 'Export Product',
            'sku' => 'ORDER-SKU-1',
            'products_category_id' => $category->id,
            'brand_id' => $brand->id,
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 10,
            'sale_price' => 20,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/api/import-export/products/export?format=xlsx&columns=sku,name');

        $response->assertOk();

        $path = storage_path('app/testing-export-ordered-products.xlsx');
        file_put_contents($path, $response->getContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

        $this->assertSame('SKU', $sheet->getCell('A1')->getValue());
        $this->assertSame('Name', $sheet->getCell('B1')->getValue());
        $this->assertSame('ORDER-SKU-1', $sheet->getCell('A2')->getValue());
        $this->assertSame('Export Product', $sheet->getCell('B2')->getValue());
    }

    public function test_product_template_with_custom_columns_omits_hidden_columns(): void
    {
        $response = $this->actingAs($this->admin)
            ->get('/api/import-export/products/template?format=csv&columns=name,sku');

        $response->assertOk();

        $lines = array_filter(explode("\n", trim($response->getContent())));
        $this->assertSame(['name', 'sku'], str_getcsv($lines[0]));
    }

    public function test_invalid_columns_param_returns_422(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/import-export/products/export?columns=not_a_column');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['columns']);
    }

    public function test_import_works_with_reordered_columns_in_file(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'sku,name,part_number,stock_quantity,low_stock_alarm,category_name,cost_currency,sale_currency,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                "SKU-REORDER,Helmet A,P-100,5,1,{$category->name},EGP,EGP,100,150,{$brand->name},fixed,0,yes,Ready",
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-REORDER']);
    }

    public function test_parse_response_columns_follow_requested_order(): void
    {
        $brand = Brand::create(['name' => 'Shoei', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Helmets']);

        $response = $this->actingAs($this->admin)->post(
            '/api/import-export/products/parse?columns=sku,name,category_name',
            [
                'file' => $this->csvUpload(implode("\n", [
                    'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,cost_currency,sale_currency,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes',
                    "Helmet A,SKU-100,P-100,5,1,{$category->name},EGP,EGP,100,150,{$brand->name},fixed,0,yes,Ready",
                ])),
            ],
        );

        $response->assertOk()
            ->assertJsonPath('columns.0.key', 'sku')
            ->assertJsonPath('columns.1.key', 'name')
            ->assertJsonPath('columns.2.key', 'category_name');
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
