<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportReportService
{
    public function export(string $reportName, array $data, string $format): Response|StreamedResponse
    {
        return match ($format) {
            'csv', 'excel' => $this->exportSpreadsheet($reportName, $data, $format),
            'pdf' => $this->exportPdf($reportName, $data),
            default => response()->json($data),
        };
    }

    private function exportSpreadsheet(string $reportName, array $data, string $format): StreamedResponse
    {
        $flattenedRows = $this->flattenReport($data);
        $delimiter = $format === 'excel' ? "\t" : ',';
        $extension = $format === 'excel' ? 'xls' : 'csv';
        $headers = ['key', 'value'];

        return response()->streamDownload(function () use ($headers, $flattenedRows, $delimiter): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers, $delimiter);

            foreach ($flattenedRows as $row) {
                fputcsv($handle, [$row['key'], $row['value']], $delimiter);
            }

            fclose($handle);
        }, $reportName.'.'.$extension, [
            'Content-Type' => $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv',
        ]);
    }

    private function exportPdf(string $reportName, array $data): Response
    {
        $lines = $this->flattenReport($data);
        $content = "%PDF-1.3\n";
        $text = strtoupper(str_replace('-', ' ', $reportName))."\n";

        foreach ($lines as $line) {
            $text .= $line['key'].': '.$line['value']."\n";
        }

        $stream = "BT /F1 10 Tf 40 780 Td (".$this->escapePdfText($text).") Tj ET";
        $objects = [];
        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
        $objects[] = "2 0 obj << /Type /Pages /Count 1 /Kids [3 0 R] >> endobj";
        $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj";
        $objects[] = "4 0 obj << /Length ".strlen($stream)." >> stream\n".$stream."\nendstream endobj";
        $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";

        $pdf = $content;
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer << /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$reportName.'.pdf"',
        ]);
    }

    private function flattenReport(array $data, string $prefix = ''): array
    {
        $rows = [];

        foreach ($data as $key => $value) {
            $label = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $rows = array_merge($rows, $this->flattenReport($value, $label));
                continue;
            }

            $rows[] = [
                'key' => $label,
                'value' => $value,
            ];
        }

        return $rows;
    }

    private function escapePdfText(string $text): string
    {
        $text = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $text);

        return str_replace(["\r", "\n"], [' ', ' '], $text);
    }
}
