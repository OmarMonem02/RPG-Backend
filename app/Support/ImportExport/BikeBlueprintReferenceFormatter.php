<?php

namespace App\Support\ImportExport;

use App\Models\BikeBlueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BikeBlueprintReferenceFormatter
{
    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2100;

    /**
     * @return array{ok: true, years: list<int>}|array{ok: false, message: string}
     */
    public function tryParseYearPart(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return ['ok' => false, 'message' => 'Year is required.'];
        }

        if (preg_match('/^\((\d{4})-(\d{4})\)$/', $value, $matches)) {
            $from = (int) $matches[1];
            $to = (int) $matches[2];

            if ($from > $to) {
                return ['ok' => false, 'message' => "Year range '{$value}' is invalid (start year must be less than or equal to end year)."];
            }

            if ($from < self::MIN_YEAR || $to > self::MAX_YEAR) {
                return ['ok' => false, 'message' => "Year range '{$value}' is outside the allowed range (" . self::MIN_YEAR . '-' . self::MAX_YEAR . ').'];
            }

            return ['ok' => true, 'years' => range($from, $to)];
        }

        if (preg_match('/^\((\d{4})\)$/', $value, $matches)) {
            $year = (int) $matches[1];

            if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                return ['ok' => false, 'message' => "Year '{$year}' is outside the allowed range (" . self::MIN_YEAR . '-' . self::MAX_YEAR . ').'];
            }

            return ['ok' => true, 'years' => [$year]];
        }

        if (preg_match('/^\d{4}$/', $value)) {
            $year = (int) $value;

            if ($year < self::MIN_YEAR || $year > self::MAX_YEAR) {
                return ['ok' => false, 'message' => "Year '{$year}' is outside the allowed range (" . self::MIN_YEAR . '-' . self::MAX_YEAR . ').'];
            }

            return ['ok' => true, 'years' => [$year]];
        }

        return ['ok' => false, 'message' => "Year '{$value}' must be a four-digit year or a range like (2020-2025)."];
    }

    /**
     * @param  Collection<int, BikeBlueprint>  $blueprints
     */
    public function formatCollection(Collection $blueprints): string
    {
        if ($blueprints->isEmpty()) {
            return '';
        }

        $grouped = $blueprints
            ->filter(fn (BikeBlueprint $bp) => $bp->brand?->name && $bp->model && $bp->year)
            ->groupBy(fn (BikeBlueprint $bp) => Str::lower(trim((string) $bp->brand?->name)) . '|' . Str::lower(trim((string) $bp->model)));

        $entries = [];

        foreach ($grouped as $group) {
            /** @var BikeBlueprint $first */
            $first = $group->first();
            $brandName = trim((string) $first->brand?->name);
            $model = trim((string) $first->model);
            $years = $group->pluck('year')->map(fn ($year) => (int) $year)->unique()->sort()->values()->all();

            foreach ($this->collapseYears($years) as [$from, $to]) {
                $yearPart = $from === $to ? (string) $from : "({$from}-{$to})";
                $entries[] = "{$brandName} | {$model} | {$yearPart}";
            }
        }

        return implode('; ', $entries);
    }

    /**
     * @param  list<int>  $years
     * @return list<array{0: int, 1: int}>
     */
    private function collapseYears(array $years): array
    {
        if ($years === []) {
            return [];
        }

        sort($years);
        $ranges = [];
        $start = $years[0];
        $end = $years[0];

        for ($i = 1, $count = count($years); $i < $count; $i++) {
            if ($years[$i] === $end + 1) {
                $end = $years[$i];
                continue;
            }

            $ranges[] = [$start, $end];
            $start = $years[$i];
            $end = $years[$i];
        }

        $ranges[] = [$start, $end];

        return $ranges;
    }
}
