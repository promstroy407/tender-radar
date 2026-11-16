<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Estimates\NormativeTotalsExtractor;
use App\Services\Estimates\NumberParser;
use PHPUnit\Framework\TestCase;

class NormativeTotalsExtractorTest extends TestCase
{
    public function test_grand_style_totals_with_equipment(): void
    {
        $totals = $this->extractor()->extract([[
            ['Итого по смете:', null, '500 000,00'],
            ['  в том числе:', null, null],
            ['  Материалы', null, '120 000,00'],
            ['  Оборудование', null, '80 000,00'],
            ['Итого по локальному сметному расчету', null, '510 000,00'],
        ]]);

        // «Итого по локальному сметному расчету» надёжнее «Итого по смете».
        $this->assertNotNull($totals);
        $this->assertSame(51_000_000, $totals->totalKopecks);
        $this->assertSame(31_000_000, $totals->worksKopecks());
    }

    public function test_table_without_materials_is_not_an_estimate(): void
    {
        $this->assertNull($this->extractor()->extract([[
            ['Наименование', 'Кол-во'],
            ['Кабель', 10],
            ['Всего по смете', 1000],
        ]]));
    }

    public function test_materials_bigger_than_total_is_rejected(): void
    {
        $this->assertNull($this->extractor()->extract([[
            ['Всего по смете', 100],
            ['Материалы', 500],
        ]]));
    }

    private function extractor(): NormativeTotalsExtractor
    {
        return new NormativeTotalsExtractor(new NumberParser);
    }
}
