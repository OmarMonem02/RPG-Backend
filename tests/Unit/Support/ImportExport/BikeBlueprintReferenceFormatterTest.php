<?php

namespace Tests\Unit\Support\ImportExport;

use App\Models\BikeBlueprint;
use App\Models\Brand;
use App\Support\ImportExport\BikeBlueprintReferenceFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BikeBlueprintReferenceFormatterTest extends TestCase
{
    use RefreshDatabase;

    private BikeBlueprintReferenceFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new BikeBlueprintReferenceFormatter();
    }

    public function test_parse_single_year(): void
    {
        $result = $this->formatter->tryParseYearPart('2020');

        $this->assertTrue($result['ok']);
        $this->assertSame([2020], $result['years']);
    }

    public function test_parse_year_range(): void
    {
        $result = $this->formatter->tryParseYearPart('(2020-2025)');

        $this->assertTrue($result['ok']);
        $this->assertSame([2020, 2021, 2022, 2023, 2024, 2025], $result['years']);
    }

    public function test_parse_single_year_in_parentheses(): void
    {
        $result = $this->formatter->tryParseYearPart('(2020)');

        $this->assertTrue($result['ok']);
        $this->assertSame([2020], $result['years']);
    }

    public function test_rejects_reversed_year_range(): void
    {
        $result = $this->formatter->tryParseYearPart('(2025-2020)');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('invalid', strtolower($result['message']));
    }

    public function test_format_collection_collapses_consecutive_years(): void
    {
        $brand = Brand::create(['name' => 'Yamaha', 'types' => ['bikes']]);
        $blueprints = collect([2020, 2021, 2022, 2023, 2024, 2025])->map(
            fn (int $year) => BikeBlueprint::create([
                'brand_id' => $brand->id,
                'model' => 'YZF-R1',
                'year' => $year,
            ])->load('brand'),
        );

        $formatted = $this->formatter->formatCollection($blueprints);

        $this->assertSame('Yamaha | YZF-R1 | (2020-2025)', $formatted);
    }

    public function test_format_collection_keeps_non_consecutive_years_separate(): void
    {
        $brand = Brand::create(['name' => 'BMW', 'types' => ['bikes']]);
        $blueprints = collect([2018, 2019, 2022])->map(
            fn (int $year) => BikeBlueprint::create([
                'brand_id' => $brand->id,
                'model' => 'S1000 RR',
                'year' => $year,
            ])->load('brand'),
        );

        $formatted = $this->formatter->formatCollection($blueprints);

        $this->assertSame('BMW | S1000 RR | (2018-2019); BMW | S1000 RR | 2022', $formatted);
    }
}
