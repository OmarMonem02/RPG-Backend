<?php

namespace App\Http\Controllers\Api;

use App\Exports\TemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\ParseImport;
use App\Imports\ProfessionalImport;
use App\Support\ImportExport\ImportExportDefinitions;
use App\Support\ImportExport\ImportRowProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ImportExportController extends Controller
{
    public function __construct(private readonly ImportExportDefinitions $definitions) {}

    public function export(Request $request, string $entity)
    {
        $config = $this->definitions->get($entity);
        $format = $this->resolveFormat($request->query('format', 'xlsx'));

        $filename = strtolower(str_replace(' ', '_', $config['label']))
            . '_' . now()->format('Ymd_His')
            . ($format === ExcelFormat::CSV ? '.csv' : '.xlsx');

        return Excel::download(new $config['export'](), $filename, $format);
    }

    public function import(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $config = $this->definitions->get($entity);
        $importer = new ProfessionalImport($entity, $config);

        $this->runExcelImport($importer, $request);

        return response()->json(
            $importer->response("Import completed for {$config['label']}.", $config['columns']),
            200,
        );
    }

    public function parse(Request $request, string $entity): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        $config = $this->definitions->get($entity);
        $data = Excel::toArray(new ParseImport(), $request->file('file'));
        $processor = new ImportRowProcessor();

        $rows = [];
        foreach (($data[0] ?? []) as $index => $row) {
            $rows[] = $processor->process($entity, $config, $row, $index + 2, false);
        }

        $summary = $processor->summarize($rows, "Preview completed for {$config['label']}.");

        return response()->json([
            'message' => $summary['message'],
            'summary' => $summary,
            'columns' => $config['columns'],
            'rows' => $rows,
            'errors' => collect($rows)->where('severity', 'error')->flatMap(fn ($row) => $row['issues'])->values()->all(),
            'warnings' => collect($rows)->where('severity', 'warning')->flatMap(fn ($row) => $row['issues'])->values()->all(),
            'duplicate_count' => $summary['duplicate_count'],
            'valid_count' => $summary['valid_count'],
            'invalid_count' => $summary['invalid_count'],
        ], 200);
    }

    public function template(Request $request, string $entity)
    {
        $config = $this->definitions->get($entity);
        $format = $this->resolveFormat($request->query('format', 'xlsx'));
        $filename = strtolower(str_replace(' ', '_', $config['label']))
            . '_template'
            . ($format === ExcelFormat::CSV ? '.csv' : '.xlsx');

        return Excel::download(new TemplateExport($config['label'], $config['columns'], $entity), $filename, $format);
    }

    public function entities(): JsonResponse
    {
        return response()->json($this->definitions->publicList());
    }

    private function resolveFormat(string $format): string
    {
        return match (strtolower($format)) {
            'csv' => ExcelFormat::CSV,
            default => ExcelFormat::XLSX,
        };
    }

    private function runExcelImport(ProfessionalImport $importer, Request $request): void
    {
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
    }
}
