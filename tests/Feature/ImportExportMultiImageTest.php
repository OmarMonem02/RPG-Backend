<?php

namespace Tests\Feature;

use App\Exports\ProductsExport;
use App\Models\InventoryImage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportExportMultiImageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_product_import_persists_multiple_image_columns(): void
    {
        $brand = Brand::create(['name' => 'Multi Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Multi Category']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,cost_currency,sale_currency,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,image_1,image_2,image_3',
                "Multi Product,MULTI-IMG-1,P-1,5,1,{$category->name},EGP,EGP,100,150,{$brand->name},fixed,0,yes,Ready,https://example.com/one.jpg,https://example.com/two.jpg,https://example.com/three.jpg",
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);

        $product = Product::with('images')->where('sku', 'MULTI-IMG-1')->first();
        $this->assertNotNull($product);
        $this->assertCount(3, $product->images);
        $this->assertSame('https://example.com/one.jpg', $product->image);
        $this->assertTrue((bool) $product->images->firstWhere('url', 'https://example.com/one.jpg')?->is_primary);
    }

    public function test_product_import_legacy_image_column_maps_to_primary(): void
    {
        $brand = Brand::create(['name' => 'Legacy Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Legacy Category']);

        $response = $this->actingAs($this->admin)->post('/api/import-export/products/import', [
            'file' => $this->csvUpload(implode("\n", [
                'name,sku,part_number,stock_quantity,low_stock_alarm,category_name,cost_currency,sale_currency,cost_price,sale_price,brand_name,max_discount_type,max_discount_value,universal,notes,image',
                "Legacy Product,LEGACY-IMG-1,P-1,5,1,{$category->name},EGP,EGP,100,150,{$brand->name},fixed,0,yes,Ready,https://example.com/legacy.jpg",
            ])),
        ]);

        $response->assertOk()->assertJsonPath('created_count', 1);

        $product = Product::with('images')->where('sku', 'LEGACY-IMG-1')->first();
        $this->assertNotNull($product);
        $this->assertSame('https://example.com/legacy.jpg', $product->image);
        $this->assertDatabaseHas('inventory_images', [
            'imageable_type' => Product::class,
            'imageable_id' => $product->id,
            'url' => 'https://example.com/legacy.jpg',
            'is_primary' => true,
        ]);
    }

    public function test_products_export_outputs_image_columns_from_inventory_images(): void
    {
        $brand = Brand::create(['name' => 'Export Brand', 'types' => ['products']]);
        $category = ProductCategory::create(['name' => 'Export Category']);

        $product = Product::create([
            'name' => 'Export Images Product',
            'sku' => 'EXPORT-IMG-1',
            'products_category_id' => $category->id,
            'brand_id' => $brand->id,
            'stock_quantity' => 1,
            'low_stock_alarm' => 0,
            'cost_currency' => 'EGP', 'sale_currency' => 'EGP',
            'cost_currency' => 'EGP',
            'sale_currency' => 'EGP',
            'cost_price' => 100,
            'sale_price' => 150,
            'max_discount_type' => 'fixed',
            'max_discount_value' => 0,
            'universal' => true,
        ]);

        InventoryImage::create([
            'imageable_type' => Product::class,
            'imageable_id' => $product->id,
            'url' => 'https://example.com/primary.jpg',
            'public_id' => 'rpg-system/products/primary',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        InventoryImage::create([
            'imageable_type' => Product::class,
            'imageable_id' => $product->id,
            'url' => 'https://example.com/secondary.jpg',
            'public_id' => 'rpg-system/products/secondary',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $export = new ProductsExport();
        $mapped = $export->map(Product::with('images')->find($product->id));

        $this->assertSame('https://example.com/primary.jpg', $mapped[21]);
        $this->assertSame('https://example.com/secondary.jpg', $mapped[22]);
        $this->assertNull($mapped[23]);
        $this->assertNull($mapped[24]);
    }

    private function csvUpload(string $contents): \Illuminate\Http\UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test-');
        file_put_contents($path, $contents);

        return new \Illuminate\Http\UploadedFile(
            $path,
            'import.csv',
            'text/csv',
            null,
            true,
        );
    }
}
