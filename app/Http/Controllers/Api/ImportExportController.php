<?php

namespace App\Http\Controllers\Api;

use App\Exports\BikeBlueprintsExport;
use App\Exports\BikesExport;
use App\Exports\BrandsExport;
use App\Exports\MaintenanceServicesExport;
use App\Exports\ProductsExport;
use App\Exports\SparePartsExport;
use App\Http\Controllers\Controller;
use App\Imports\BikeBlueprintsImport;
use App\Imports\BikesImport;
use App\Imports\BrandsImport;
use App\Imports\MaintenanceServicesImport;
use App\Imports\ProductsImport;
use App\Imports\SparePartsImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelFormat;

class ImportExportController extends Controller
{
    // -----------------------------------------------------------------------
    // Supported entities config
    // -----------------------------------------------------------------------

    /**
     * Map entity slug → [export class, import class, label, template columns]
     */
    private function entityConfig(): array
    {
        return [
            'products' => [
                'export'   => ProductsExport::class,
                'import'   => ProductsImport::class,
                'label'    => 'Products',
                'template' => [
                    'name', 'sku', 'part_number', 'stock_quantity', 'low_stock_alarm',
                    'category_id', 'currency_pricing', 'cost_price', 'sale_price',
                    'brand_id', 'max_discount_type', 'max_discount_value', 'universal', 'notes',
                ],
            ],
            'spare_parts' => [
                'export'   => SparePartsExport::class,
                'import'   => SparePartsImport::class,
                'label'    => 'Spare Parts',
                'template' => [
                    'name', 'sku', 'part_number', 'stock_quantity', 'low_stock_alarm',
                    'category_id', 'currency_pricing', 'cost_price', 'sale_price',
                    'brand_id', 'max_discount_type', 'max_discount_value', 'universal', 'notes', 'bike_blueprint_ids',
                ],
            ],
            'maintenance_services' => [
                'export'   => MaintenanceServicesExport::class,
                'import'   => MaintenanceServicesImport::class,
                'label'    => 'Maintenance Services',
                'template' => [
                    'name', 'currency_pricing', 'service_price',
                    'max_discount_type', 'max_discount_value', 'sector_id',
                ],
            ],
            'bikes' => [
                'export'   => BikesExport::class,
                'import'   => BikesImport::class,
                'label'    => 'Bikes For Sale',
                'template' => [
                    'blueprint_id', 'vin', 'mileage', 'status', 'currency_pricing',
                    'cost_price', 'sale_price', 'max_discount_type', 'max_discount_value', 'notes',
                ],
            ],
            'bike_blueprints' => [
                'export'   => BikeBlueprintsExport::class,
                'import'   => BikeBlueprintsImport::class,
                'label'    => 'Bike Blueprints',
                'template' => [
                    'brand_id', 'model', 'year',
                ],
            ],
            'brands' => [
                'export'   => BrandsExport::class,
                'import'   => BrandsImport::class,
                'label'    => 'Brands',
                'template' => [
                    'name', 'type',
                ],
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // EXPORT
    // GET /api/import-export/{entity}/export?format=xlsx|csv
    // -----------------------------------------------------------------------

    /**
     * Export all records for the given entity.
     *
     * Query params:
     *   - format : 'xlsx' (default) | 'csv'
     */
    public function export(Request $request, string $entity)
    {
        $config = $this->resolveConfig($entity);
        $format = $this->resolveFormat($request->query('format', 'xlsx'));

        $filename = strtolower(str_replace(' ', '_', $config['label']))
            . '_' . now()->format('Ymd_His')
            . ($format === ExcelFormat::CSV ? '.csv' : '.xlsx');

        return Excel::download(new $config['export'](), $filename, $format);
    }

    // -----------------------------------------------------------------------
    // IMPORT
    // POST /api/import-export/{entity}/import
    // Body: multipart/form-data  →  file (xlsx or csv)
    // -----------------------------------------------------------------------

    /**
     * Import records from an uploaded Excel or CSV file.
     *
     * Returns:
     *   - imported_count : number of successfully imported rows
     *   - errors         : array of skipped-row error messages (if any)
     */
    public function import(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240', // max 10 MB
        ]);

        $config   = $this->resolveConfig($entity);
        $importer = new $config['import']();

        try {
            Excel::import($importer, $request->file('file'));
        } catch (\ErrorException $exception) {
            $message = $exception->getMessage();

            $isExcelTempCleanupError = str_contains($message, 'laravel-excel')
                && str_contains($message, 'Permission denied')
                && str_contains($message, 'unlink(');

            if (! $isExcelTempCleanupError) {
                throw $exception;
            }
        }

        // Collect skipped-row errors (SkipsErrors trait stores them)
        $errors = collect($importer->errors())->map(fn ($e) => $e->getMessage())->values()->all();

        return response()->json([
            'message' => "Import completed for {$config['label']}.",
            'created_count' => method_exists($importer, 'createdCount') ? $importer->createdCount() : 0,
            'restored_count' => method_exists($importer, 'restoredCount') ? $importer->restoredCount() : 0,
            'skipped_count' => method_exists($importer, 'skippedCount') ? $importer->skippedCount() : 0,
            'skipped_duplicates' => method_exists($importer, 'skippedDuplicates') ? $importer->skippedDuplicates() : [],
            'restored_records' => method_exists($importer, 'restoredRecords') ? $importer->restoredRecords() : [],
            'errors'  => $errors,
        ], 200);
    }

    // -----------------------------------------------------------------------
    // PARSE FOR REVIEW
    // POST /api/import-export/{entity}/parse
    // -----------------------------------------------------------------------

    /**
     * Parse uploaded file and return array of records without saving to DB.
     * Used for front-end review steps before final import.
     */
    public function parse(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $this->resolveConfig($entity); // Ensure entity exists

        $data = Excel::toArray(new \App\Imports\ParseImport(), $request->file('file'));

        return response()->json([
            'rows' => $data[0] ?? []
        ], 200);
    }


    // -----------------------------------------------------------------------
    // TEMPLATE DOWNLOAD
    // GET /api/import-export/{entity}/template?format=xlsx|csv
    // -----------------------------------------------------------------------

    /**
     * Download an empty template file with only the header row.
     */
    public function template(Request $request, string $entity)
    {
        $config  = $this->resolveConfig($entity);
        $format  = $this->resolveFormat($request->query('format', 'xlsx'));
        $columns = $config['template'];
        $label   = $config['label'];

        $filename = strtolower(str_replace(' ', '_', $label))
            . '_template'
            . ($format === ExcelFormat::CSV ? '.csv' : '.xlsx');

        // Build an in-memory export with just the header row
        $templateExport = new \App\Exports\TemplateExport($label, $columns);

        return Excel::download($templateExport, $filename, $format);
    }

    // -----------------------------------------------------------------------
    // LIST SUPPORTED ENTITIES
    // GET /api/import-export/entities
    // -----------------------------------------------------------------------

    public function entities(): JsonResponse
    {
        $list = collect($this->entityConfig())->map(fn ($cfg, $slug) => [
            'slug'     => $slug,
            'label'    => $cfg['label'],
            'columns'  => $cfg['template'],
            'endpoints' => [
                'export'   => url("/api/import-export/{$slug}/export"),
                'import'   => url("/api/import-export/{$slug}/import"),
                'template' => url("/api/import-export/{$slug}/template"),
            ],
        ])->values();

        return response()->json($list);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function resolveConfig(string $entity): array
    {
        $config = $this->entityConfig();

        if (!isset($config[$entity])) {
            abort(404, "Entity '{$entity}' is not supported for import/export.");
        }

        return $config[$entity];
    }

    private function resolveFormat(string $format): string
    {
        return match (strtolower($format)) {
            'csv'  => ExcelFormat::CSV,
            default => ExcelFormat::XLSX,
        };
    }
}
