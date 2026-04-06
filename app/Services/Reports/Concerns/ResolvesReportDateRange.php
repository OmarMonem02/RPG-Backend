<?php

namespace App\Services\Reports\Concerns;

use Carbon\Carbon;

trait ResolvesReportDateRange
{
    protected function resolveDateRange(array $filters): array
    {
        if (! empty($filters['date'])) {
            $date = Carbon::parse($filters['date']);

            return [
                'from' => $date->copy()->startOfDay(),
                'to' => $date->copy()->endOfDay(),
            ];
        }

        return [
            'from' => Carbon::parse($filters['from_date'])->startOfDay(),
            'to' => Carbon::parse($filters['to_date'])->endOfDay(),
        ];
    }
}
