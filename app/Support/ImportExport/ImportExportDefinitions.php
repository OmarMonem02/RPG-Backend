<?php

namespace App\Support\ImportExport;

use App\Exports\BikeBlueprintsExport;
use App\Exports\BikesExport;
use App\Exports\BrandsExport;
use App\Exports\MaintenanceServicesExport;
use App\Exports\MaintenanceServiceSectorsExport;
use App\Exports\ProductCategoriesExport;
use App\Exports\ProductsExport;
use App\Exports\SparePartCategoriesExport;
use App\Exports\SparePartsExport;
use App\Models\BikeBlueprint;
use App\Models\BikeForSale;
use App\Models\Brand;
use App\Models\MaintenanceService;
use App\Models\MaintenanceServiceSector;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SparePart;
use App\Models\SparePartCategory;

class ImportExportDefinitions
{
    public function all(): array
    {
        return [
            'products' => [
                'label' => 'Products',
                'model' => Product::class,
                'export' => ProductsExport::class,
                'unique' => ['sku'],
                'duplicate_label' => 'product',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Product display name.'),
                    $this->column('sku', 'SKU', true, 'text', 'Unique product stock keeping unit.'),
                    $this->column('part_number', 'Part Number', false, 'text', 'Manufacturer or supplier part number.'),
                    $this->column('stock_quantity', 'Stock Quantity', false, 'integer', 'Current quantity on hand.'),
                    $this->column('low_stock_alarm', 'Low Stock Alarm', false, 'integer', 'Quantity threshold for low-stock alerts.'),
                    $this->column('category_name', 'Category Name', true, 'reference', 'Existing product category name.', reference: 'product_categories.name'),
                    $this->column('cost_currency', 'Cost Currency', false, 'text', 'Cost price currency: EGP, USD, or EUR.'),
                    $this->column('sale_currency', 'Sale Currency', false, 'text', 'Sale price currency: EGP, USD, or EUR.'),
                    $this->column('cost_price', 'Cost Price', false, 'decimal', 'Purchase cost.'),
                    $this->column('sale_price', 'Sale Price', false, 'decimal', 'Retail sale price.'),
                    $this->column('sale_price_mode', 'Sale Price Mode', false, 'select', 'manual or margin.', ['manual', 'margin']),
                    $this->column('sale_margin_type', 'Sale Margin Type', false, 'select', 'percentage or fixed.', ['percentage', 'fixed']),
                    $this->column('sale_margin_value', 'Sale Margin Value', false, 'decimal', 'Margin percentage or fixed EGP amount.'),
                    $this->column('brand_name', 'Brand Name', true, 'reference', 'Existing product brand name.', reference: 'brands.name'),
                    $this->column('max_discount_type', 'Max Discount Type', false, 'select', 'fixed or percentage.', ['fixed', 'percentage']),
                    $this->column('max_discount_value', 'Max Discount Value', false, 'decimal', 'Maximum discount amount or percentage.'),
                    $this->column('universal', 'Universal', false, 'boolean', 'Yes/No, true/false, or 1/0.'),
                    $this->column('notes', 'Notes', false, 'text', 'Internal notes.'),
                    $this->column('bike_blueprints', 'Compatible Bike Blueprints', false, 'reference_list', 'Optional list: Brand | Model | Year; Brand | Model | Year.', reference: 'bike_blueprints.brand_model_year'),
                    $this->column('tags', 'Tags', false, 'tag_list', 'Semicolon-separated tags, e.g. Matte Black; High Load'),
                    $this->column('image_1', 'Image 1 (Primary)', false, 'url', 'Primary product image URL (optional).'),
                    $this->column('image_2', 'Image 2', false, 'url', 'Additional product image URL (optional).'),
                    $this->column('image_3', 'Image 3', false, 'url', 'Additional product image URL (optional).'),
                    $this->column('image_4', 'Image 4', false, 'url', 'Additional product image URL (optional).'),
                ],
            ],
            'spare_parts' => [
                'label' => 'Spare Parts',
                'model' => SparePart::class,
                'export' => SparePartsExport::class,
                'unique' => ['sku'],
                'duplicate_label' => 'spare part',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Spare part display name.'),
                    $this->column('sku', 'SKU', true, 'text', 'Unique spare part SKU.'),
                    $this->column('part_number', 'Part Number', false, 'text', 'Manufacturer or supplier part number.'),
                    $this->column('stock_quantity', 'Stock Quantity', false, 'integer', 'Current quantity on hand.'),
                    $this->column('low_stock_alarm', 'Low Stock Alarm', false, 'integer', 'Quantity threshold for low-stock alerts.'),
                    $this->column('category_name', 'Category Name', true, 'reference', 'Existing spare part category name.', reference: 'spare_part_categories.name'),
                    $this->column('cost_currency', 'Cost Currency', false, 'text', 'Cost price currency: EGP, USD, or EUR.'),
                    $this->column('sale_currency', 'Sale Currency', false, 'text', 'Sale price currency: EGP, USD, or EUR.'),
                    $this->column('cost_price', 'Cost Price', false, 'decimal', 'Purchase cost.'),
                    $this->column('sale_price', 'Sale Price', false, 'decimal', 'Retail sale price.'),
                    $this->column('sale_price_mode', 'Sale Price Mode', false, 'select', 'manual or margin.', ['manual', 'margin']),
                    $this->column('sale_margin_type', 'Sale Margin Type', false, 'select', 'percentage or fixed.', ['percentage', 'fixed']),
                    $this->column('sale_margin_value', 'Sale Margin Value', false, 'decimal', 'Margin percentage or fixed EGP amount.'),
                    $this->column('brand_name', 'Brand Name', true, 'reference', 'Existing spare part brand name.', reference: 'brands.name'),
                    $this->column('max_discount_type', 'Max Discount Type', false, 'select', 'fixed or percentage.', ['fixed', 'percentage']),
                    $this->column('max_discount_value', 'Max Discount Value', false, 'decimal', 'Maximum discount amount or percentage.'),
                    $this->column('universal', 'Universal', false, 'boolean', 'Yes/No, true/false, or 1/0.'),
                    $this->column('notes', 'Notes', false, 'text', 'Internal notes.'),
                    $this->column('bike_blueprints', 'Compatible Bike Blueprints', false, 'reference_list', 'Optional list: Brand | Model | Year; Brand | Model | Year.', reference: 'bike_blueprints.brand_model_year'),
                    $this->column('tags', 'Tags', false, 'tag_list', 'Semicolon-separated tags, e.g. Matte Black; High Load'),
                    $this->column('image_1', 'Image 1 (Primary)', false, 'url', 'Primary spare part image URL (optional).'),
                    $this->column('image_2', 'Image 2', false, 'url', 'Additional spare part image URL (optional).'),
                    $this->column('image_3', 'Image 3', false, 'url', 'Additional spare part image URL (optional).'),
                    $this->column('image_4', 'Image 4', false, 'url', 'Additional spare part image URL (optional).'),
                ],
            ],
            'maintenance_services' => [
                'label' => 'Maintenance Services',
                'model' => MaintenanceService::class,
                'export' => MaintenanceServicesExport::class,
                'unique' => ['name', 'sector_name'],
                'duplicate_label' => 'maintenance service',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Service name.'),
                    $this->column('sale_currency', 'Sale Currency', false, 'text', 'Pricing currency: EGP, USD, or EUR.'),
                    $this->column('service_price', 'Service Price', false, 'decimal', 'Service retail price.'),
                    $this->column('max_discount_type', 'Max Discount Type', false, 'select', 'fixed or percentage.', ['fixed', 'percentage']),
                    $this->column('max_discount_value', 'Max Discount Value', false, 'decimal', 'Maximum discount amount or percentage.'),
                    $this->column('sector_name', 'Sector Name', true, 'reference', 'Existing maintenance sector name.', reference: 'maintenance_service_sectors.name'),
                ],
            ],
            'bikes' => [
                'label' => 'Bikes For Sale',
                'model' => BikeForSale::class,
                'export' => BikesExport::class,
                'unique' => ['vin'],
                'duplicate_label' => 'bike',
                'columns' => [
                    $this->column('brand_name', 'Brand Name', true, 'reference', 'Existing bike brand name.', reference: 'brands.name'),
                    $this->column('model', 'Model', true, 'text', 'Existing bike blueprint model.'),
                    $this->column('year', 'Year', true, 'integer', 'Existing bike blueprint year.'),
                    $this->column('vin', 'VIN', true, 'text', 'Unique vehicle identification number.'),
                    $this->column('mileage', 'Mileage', false, 'integer', 'Current mileage.'),
                    $this->column('status', 'Status', false, 'select', 'Bike sales status.', ['available', 'sold', 'maintenance', 'reserved']),
                    $this->column('cost_currency', 'Cost Currency', false, 'text', 'Cost price currency: EGP, USD, or EUR.'),
                    $this->column('sale_currency', 'Sale Currency', false, 'text', 'Sale price currency: EGP, USD, or EUR.'),
                    $this->column('cost_price', 'Cost Price', false, 'decimal', 'Purchase cost.'),
                    $this->column('sale_price', 'Sale Price', false, 'decimal', 'Retail sale price.'),
                    $this->column('sale_price_mode', 'Sale Price Mode', false, 'select', 'manual or margin.', ['manual', 'margin']),
                    $this->column('sale_margin_type', 'Sale Margin Type', false, 'select', 'percentage or fixed.', ['percentage', 'fixed']),
                    $this->column('sale_margin_value', 'Sale Margin Value', false, 'decimal', 'Margin percentage or fixed EGP amount.'),
                    $this->column('max_discount_type', 'Max Discount Type', false, 'select', 'fixed or percentage.', ['fixed', 'percentage']),
                    $this->column('max_discount_value', 'Max Discount Value', false, 'decimal', 'Maximum discount amount or percentage.'),
                    $this->column('notes', 'Notes', false, 'text', 'Internal notes.'),
                    $this->column('image_1', 'Image 1 (Primary)', false, 'url', 'Primary bike image URL (optional).'),
                    $this->column('image_2', 'Image 2', false, 'url', 'Additional bike image URL (optional).'),
                    $this->column('image_3', 'Image 3', false, 'url', 'Additional bike image URL (optional).'),
                    $this->column('image_4', 'Image 4', false, 'url', 'Additional bike image URL (optional).'),
                ],
            ],
            'bike_blueprints' => [
                'label' => 'Bike Blueprints',
                'model' => BikeBlueprint::class,
                'export' => BikeBlueprintsExport::class,
                'unique' => ['brand_name', 'model', 'year'],
                'duplicate_label' => 'bike blueprint',
                'columns' => [
                    $this->column('brand_name', 'Brand Name', true, 'reference', 'Existing bike brand name.', reference: 'brands.name'),
                    $this->column('model', 'Model', true, 'text', 'Bike model.'),
                    $this->column('year', 'Year', true, 'integer', 'Model year.'),
                ],
            ],
            'brands' => [
                'label' => 'Brands',
                'model' => Brand::class,
                'export' => BrandsExport::class,
                'unique' => ['name'],
                'duplicate_label' => 'brand',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Brand name.'),
                    $this->column('types', 'Types', true, 'reference_list', 'Comma-separated: products, spare_parts, bikes.', reference: 'brands.types'),
                ],
            ],
            'product_categories' => [
                'label' => 'Product Categories',
                'model' => ProductCategory::class,
                'export' => ProductCategoriesExport::class,
                'unique' => ['name'],
                'duplicate_label' => 'product category',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Product category name.'),
                ],
            ],
            'spare_part_categories' => [
                'label' => 'Spare Part Categories',
                'model' => SparePartCategory::class,
                'export' => SparePartCategoriesExport::class,
                'unique' => ['name'],
                'duplicate_label' => 'spare part category',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Spare part category name.'),
                ],
            ],
            'maintenance_service_sectors' => [
                'label' => 'Maintenance Sectors',
                'model' => MaintenanceServiceSector::class,
                'export' => MaintenanceServiceSectorsExport::class,
                'unique' => ['name'],
                'duplicate_label' => 'maintenance sector',
                'columns' => [
                    $this->column('name', 'Name', true, 'text', 'Maintenance service sector name.'),
                ],
            ],
        ];
    }

    public function get(string $entity): array
    {
        $definitions = $this->all();

        if (! isset($definitions[$entity])) {
            abort(404, "Entity '{$entity}' is not supported for import/export.");
        }

        return $definitions[$entity];
    }

    public function publicList(): array
    {
        return collect($this->all())->map(fn (array $definition, string $slug) => [
            'slug' => $slug,
            'label' => $definition['label'],
            'columns' => $definition['columns'],
            'endpoints' => [
                'export' => "/import-export/{$slug}/export",
                'import' => "/import-export/{$slug}/import",
                'parse' => "/import-export/{$slug}/parse",
                'template' => "/import-export/{$slug}/template",
            ],
        ])->values()->all();
    }

    private function column(
        string $key,
        string $label,
        bool $required,
        string $type,
        string $description,
        array $acceptedValues = [],
        ?string $reference = null,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'required' => $required,
            'type' => $type,
            'description' => $description,
            'accepted_values' => $acceptedValues,
            'reference' => $reference,
        ];
    }
}
